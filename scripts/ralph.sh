#!/usr/bin/env bash
#
# Ralph loop: works through the unblocked `ready-for-agent` GitHub issues, one
# fresh Claude Code session per issue, each reachable through Remote Control
# (claude.ai/code or the Claude app) under the name ralph-issue-<number>.
#
#   scripts/ralph.sh [--ask] [--merge] [--max N] [--timeout MINUTES] [--dry-run]
#
#   --ask              Sessions ask you about decisions and wait for your reply
#                      through Remote Control, instead of deciding themselves.
#                      Waiting counts towards --timeout.
#   --merge            Squash-merge each PR into main once its CI passes, then
#                      update this checkout, so the next issue builds on it and
#                      blocked issues unlock in the same run. Stops if CI fails.
#   --max N            Issues to work before stopping (default 5)
#   --timeout MINUTES  Time allowed per issue (default 120)
#   --dry-run          List the issues that are ready now, then exit
#
# An issue is ready when it is open, labelled `ready-for-agent`, unassigned and
# has no open blocker. Claiming assigns it, so it is never picked twice; unassign
# it to retry. Each issue gets a branch and a PR against main. Without --merge
# nothing is merged, so an issue blocked by another one waits for you to merge
# that PR.
#
# Remote Control needs an interactive session, so `claude -p` can't be used and
# each session runs in tmux. An interactive session never exits by itself, so
# the loop watches GitHub instead:
#   - an open PR from the issue's branch means done: the session is closed
#   - `ready-for-agent` removed means stuck: the loop stops
#   - the session exiting, or the timeout, also stops the loop
# When the loop stops, the session stays open: take over with Remote Control or
# `tmux attach -t ralph-issue-<number>`.
#
# The sessions work in this checkout (DDEV is tied to it), so leave it alone
# while the loop runs.

set -euo pipefail

CLAUDE_BIN="${CLAUDE_BIN:-$(command -v claude || echo "$HOME/.local/bin/claude")}"
READY_LABEL=ready-for-agent
POLL_SECONDS=30
PR_GRACE_SECONDS=60

max_issues=5
timeout_minutes=120
dry_run=false
ask_maintainer=false
merge_prs=false

log() {
    printf '[ralph %s] %s\n' "$(date +%H:%M:%S)" "$*"
}

die() {
    log "$*" >&2
    exit 1
}

# Prints the ready issue numbers, oldest first.
ready_issues() {
    local number blockers
    for number in $(gh issue list --state open --label "$READY_LABEL" --limit 100 \
        --json number,assignees \
        --jq 'map(select(.assignees | length == 0)) | sort_by(.number) | .[].number'); do
        blockers=$(gh api "repos/{owner}/{repo}/issues/$number" --jq '.issue_dependencies_summary.blocked_by')
        if [[ "$blockers" == 0 ]]; then
            echo "$number"
        fi
    done
}

issue_prompt() {
    local number=$1 branch=$2 decisions when_blocked

    if [[ "$ask_maintainer" == true ]]; then
        decisions="The maintainer is taking part through Remote Control. When a decision is not settled by the issue, GLOSSARY.md or docs/adr/ (scope, behaviour, data model, UX), ask them in a plain message, end your turn and wait for their reply. Decide routine implementation details yourself, and stay within this issue."
        when_blocked="If you cannot finish (the issue is unclear, a check stays red, or it needs a human decision), do not open a PR. Ask the maintainer here and wait. Only if they tell you to give up, comment on the issue with what blocked you, swap its label from $READY_LABEL to ready-for-human, then stop."
    else
        decisions="The maintainer may join through Remote Control, and their messages take priority, but do not wait for them: make reasonable decisions yourself and stay within this issue."
        when_blocked="If you cannot finish (the issue is unclear, a check stays red, or it needs a human decision), do not open a PR. Comment on the issue with what blocked you, swap its label from $READY_LABEL to ready-for-human, then stop. The maintainer can reply here through Remote Control."
    fi

    cat <<PROMPT
/mattpocock-skills:implement GitHub issue #$number.

You are in a loop, on branch $branch, which is already checked out. $decisions

When the work is committed:
1. Run the full suite with \`ddev artisan test --compact\`, and the checks under "Before opening a PR" in AGENTS.md. Fix everything they report.
2. Push the branch and open a PR against main, titled in the style of recent merged PRs, with the body written using the mattpocock-skills:pr skill and containing "Closes #$number". Do not merge it.

Opening the PR must be your last action: the loop closes this session as soon as it sees the PR.

$when_blocked
PROMPT
}

start_session() {
    local number=$1 branch=$2 session=$3 url
    tmux new-session -d -s "$session" -x 200 -y 50 -c "$PWD" \
        "$CLAUDE_BIN" "--remote-control=$session" --permission-mode auto \
        "$(issue_prompt "$number" "$branch")"

    sleep 10
    url=$(tmux capture-pane -pJt "$session" 2>/dev/null | grep -o 'https://claude\.ai/code/session_[A-Za-z0-9]*' | head -1 || true)
    log "#$number: session $session started ${url:-(no Remote Control link yet, find it by name)}"
}

# Prints done, stuck, exited or timeout.
wait_for_outcome() {
    local number=$1 branch=$2 session=$3 deadline pr_url labels
    deadline=$((SECONDS + timeout_minutes * 60))

    while ((SECONDS < deadline)); do
        pr_url=$(gh pr list --head "$branch" --state open --json url --jq '.[0].url // ""' || true)
        if [[ -n "$pr_url" ]]; then
            log "#$number: PR opened: $pr_url" >&2
            echo done
            return
        fi

        labels=$(gh issue view "$number" --json labels --jq '.labels[].name' || echo "$READY_LABEL")
        if ! grep -qx "$READY_LABEL" <<<"$labels"; then
            echo stuck
            return
        fi

        if ! tmux has-session -t "$session" 2>/dev/null; then
            echo exited
            return
        fi

        sleep "$POLL_SECONDS"
    done

    echo timeout
}

# Waits for CI, merges the PR and closes the issue. Closing it here, rather than
# leaving it to "Closes #n", means its dependents are unblocked straight away.
merge_pr() {
    local number=$1 branch=$2

    log "#$number: waiting for CI"
    if ! timeout "$((timeout_minutes * 60))" gh pr checks "$branch" --watch --fail-fast >/dev/null; then
        die "#$number: CI did not pass, PR left open and not merged"
    fi

    gh pr merge "$branch" --squash --delete-branch >/dev/null
    gh issue close "$number" >/dev/null
    log "#$number: PR merged"
}

# Brings this checkout up to the merged main.
sync_checkout() {
    git switch --quiet main
    git pull --quiet --ff-only origin main
    ddev composer install --no-interaction --quiet
    ddev npm ci --silent
    ddev artisan migrate --no-interaction
}

work_issue() {
    local number=$1 branch="feat/issue-$1" session="ralph-issue-$1" outcome

    git switch --quiet main
    git pull --quiet --ff-only origin main
    git switch --quiet -c "$branch"
    gh issue edit "$number" --add-assignee @me >/dev/null
    log "#$number: claimed, working on $branch"

    start_session "$number" "$branch" "$session"
    outcome=$(wait_for_outcome "$number" "$branch" "$session")

    if [[ "$outcome" != done ]]; then
        die "#$number: stopping, outcome is '$outcome'. Session: tmux attach -t $session"
    fi

    sleep "$PR_GRACE_SECONDS"
    tmux kill-session -t "$session" 2>/dev/null || true

    if [[ -n "$(git status --porcelain)" ]]; then
        die "#$number: PR is open but the working tree is dirty, stopping"
    fi

    if [[ "$merge_prs" == true ]]; then
        merge_pr "$number" "$branch"
        sync_checkout
    fi
}

main() {
    while (($# > 0)); do
        case "$1" in
            --max) max_issues=$2; shift 2 ;;
            --timeout) timeout_minutes=$2; shift 2 ;;
            --dry-run) dry_run=true; shift ;;
            --ask) ask_maintainer=true; shift ;;
            --merge) merge_prs=true; shift ;;
            *) die "Unknown option: $1" ;;
        esac
    done

    cd "$(git rev-parse --show-toplevel)"

    local ready worked=0
    if [[ "$dry_run" == true ]]; then
        mapfile -t ready < <(ready_issues)
        log "Ready: ${ready[*]:-none}"
        return
    fi

    command -v tmux >/dev/null || die "tmux is required"
    [[ -x "$CLAUDE_BIN" ]] || die "claude not found, set CLAUDE_BIN"
    ddev exec true >/dev/null 2>&1 || die "DDEV is not running, run: ddev start"
    [[ -z "$(git status --porcelain)" ]] || die "Working tree is not clean"

    while ((worked < max_issues)); do
        mapfile -t ready < <(ready_issues)
        if ((${#ready[@]} == 0)); then
            log "No ready issues left"
            break
        fi

        work_issue "${ready[0]}"
        worked=$((worked + 1))
    done

    git switch --quiet main
    log "Finished: $worked issue(s) worked"
}

main "$@"
