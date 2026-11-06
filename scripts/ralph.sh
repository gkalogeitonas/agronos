#!/usr/bin/env bash
#
# Ralph loop: works through the unblocked `ready-for-agent` GitHub issues, one
# fresh Claude Code session per issue, each reachable through Remote Control
# (claude.ai/code or the Claude app) under the name ralph-issue-<number>.
#
#   scripts/ralph.sh [--spec N] [--ask] [--merge] [--max N] [--timeout MINUTES] [--dry-run]
#
#   --spec N           Work only the sub-issues of spec issue N. Each one is merged
#                      locally with --no-ff into the branch spec/issue-N, with no
#                      PR of its own. When no issue is ready, opens one PR from
#                      that branch to main and stops; merging it is up to you,
#                      with a merge commit, and it closes the spec and its issues.
#                      Issues stay open until then, so a blocker counts as
#                      resolved once it is merged into the spec branch.
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
# it to retry. Without --spec, each issue gets a branch and a PR against main.
# Without --merge too, nothing is merged, so an issue blocked by another one
# waits for you to merge that PR.
#
# Remote Control needs an interactive session, so `claude -p` can't be used and
# each session runs in tmux. An interactive session never exits by itself, so
# the loop watches GitHub instead:
#   - an open PR from the issue's branch means done: the session is closed.
#     With --spec, the branch appearing on origin means done.
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
spec_issue=""
base_branch=main

log() {
    printf '[ralph %s] %s\n' "$(date +%H:%M:%S)" "$*"
}

die() {
    log "$*" >&2
    exit 1
}

# Prints the open, unassigned issues labelled ready, oldest first.
candidate_issues() {
    if [[ -n "$spec_issue" ]]; then
        gh api "repos/{owner}/{repo}/issues/$spec_issue/sub_issues?per_page=100" --jq "
            map(select(.state == \"open\" and (.assignees | length == 0)
                and (.labels | map(.name) | index(\"$READY_LABEL\"))))
            | sort_by(.number) | .[].number"
    else
        gh issue list --state open --label "$READY_LABEL" --limit 100 \
            --json number,assignees \
            --jq 'map(select(.assignees | length == 0)) | sort_by(.number) | .[].number'
    fi
}

# Prints the issues merged into the spec branch, read from its merge commits.
merged_issues() {
    if [[ -n "$spec_issue" ]]; then
        git log --merges --first-parent --format=%s "origin/main..origin/$base_branch" 2>/dev/null |
            sed -n 's/^Merge feat\/issue-\([0-9]*\) .*/\1/p' || true
    fi
}

# Succeeds when every open blocker of the issue is in the merged list.
blockers_resolved() {
    local number=$1 merged=$2 blocker
    for blocker in $(gh api "repos/{owner}/{repo}/issues/$number/dependencies/blocked_by?per_page=100" \
        --jq '.[] | select(.state == "open") | .number'); do
        if ! grep -qx "$blocker" <<<"$merged"; then
            return 1
        fi
    done
}

# Prints the ready issue numbers: candidates not yet merged, with no blocker left.
ready_issues() {
    local number merged
    merged=$(merged_issues)
    for number in $(candidate_issues); do
        if ! grep -qx "$number" <<<"$merged" && blockers_resolved "$number" "$merged"; then
            echo "$number"
        fi
    done
}

issue_prompt() {
    local number=$1 branch=$2 decisions when_blocked finish_step finish_note hold_back

    if [[ -n "$spec_issue" ]]; then
        finish_step="Push the branch with \`git push -u origin $branch\`, once, when everything is green. Do not open a PR: the loop merges the branch into $base_branch."
        finish_note="Pushing the branch must be your last action: the loop closes this session as soon as it sees the branch on origin."
        hold_back="do not push the branch"
    else
        finish_step="Push the branch and open a PR against $base_branch, titled in the style of recent merged PRs, with the body written using the mattpocock-skills:pr skill and containing \"Closes #$number\". Do not merge it."
        finish_note="Opening the PR must be your last action: the loop closes this session as soon as it sees the PR."
        hold_back="do not open a PR"
    fi

    if [[ "$ask_maintainer" == true ]]; then
        decisions="The maintainer is taking part through Remote Control. When a decision is not settled by the issue, GLOSSARY.md or docs/adr/ (scope, behaviour, data model, UX), ask them in a plain message, end your turn and wait for their reply. Decide routine implementation details yourself, and stay within this issue."
        when_blocked="If you cannot finish (the issue is unclear, a check stays red, or it needs a human decision), $hold_back. Ask the maintainer here and wait. Only if they tell you to give up, comment on the issue with what blocked you, swap its label from $READY_LABEL to ready-for-human, then stop."
    else
        decisions="The maintainer may join through Remote Control, and their messages take priority, but do not wait for them: make reasonable decisions yourself and stay within this issue."
        when_blocked="If you cannot finish (the issue is unclear, a check stays red, or it needs a human decision), $hold_back. Comment on the issue with what blocked you, swap its label from $READY_LABEL to ready-for-human, then stop. The maintainer can reply here through Remote Control."
    fi

    cat <<PROMPT
/mattpocock-skills:implement GitHub issue #$number.

You are in a loop, on branch $branch, which is already checked out. $decisions

When the work is committed:
1. Run the full suite with \`ddev artisan test --compact\`, and the checks under "Before opening a PR" in AGENTS.md. Fix everything they report.
2. $finish_step

$finish_note

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
    local number=$1 branch=$2 session=$3 deadline finished labels
    deadline=$((SECONDS + timeout_minutes * 60))

    while ((SECONDS < deadline)); do
        if [[ -n "$spec_issue" ]]; then
            finished=$(git ls-remote --heads origin "$branch" || true)
        else
            finished=$(gh pr list --head "$branch" --state open --json url --jq '.[0].url // ""' || true)
        fi

        if [[ -n "$finished" ]]; then
            log "#$number: finished: ${finished##*[[:space:]]}" >&2
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
    gh issue close "$number" --comment "Merged into \`$base_branch\`." >/dev/null
    log "#$number: PR merged"
}

# Merges a finished issue into the spec branch and removes its branch.
merge_into_spec() {
    local number=$1 branch=$2

    update_base_branch
    if ! git merge --quiet --no-ff -m "Merge $branch into $base_branch (#$number)" "$branch"; then
        git merge --abort
        die "#$number: could not merge $branch into $base_branch"
    fi

    git push --quiet origin "$base_branch"
    git push --quiet origin --delete "$branch"
    git branch --quiet -d "$branch"
    log "#$number: merged into $base_branch"
}

# Checks out the base branch at its latest commit.
update_base_branch() {
    git fetch --quiet origin
    git switch --quiet "$base_branch"
    git pull --quiet --ff-only origin "$base_branch"
}

# Brings this checkout up to the merged base branch.
sync_checkout() {
    update_base_branch
    ddev composer install --no-interaction --quiet
    ddev npm ci --silent
    ddev artisan migrate --no-interaction
}

# Creates the spec branch from main the first time a spec is worked.
ensure_spec_branch() {
    git fetch --quiet origin
    if ! git ls-remote --exit-code --heads origin "$base_branch" >/dev/null; then
        git push --quiet origin "origin/main:refs/heads/$base_branch"
        git fetch --quiet origin
        log "Created $base_branch from main"
    fi
}

# Opens the PR from the spec branch to main, or refreshes its body if it is open.
# A spec with sub-issues still unmerged is only "Part of", so it stays open.
open_spec_pr() {
    local merged number remaining="" spec_link=Closes closes="" body pr_url title

    git fetch --quiet origin
    if [[ "$(git rev-list --count "origin/main..origin/$base_branch")" == 0 ]]; then
        log "$base_branch has nothing to merge, no PR opened"
        return
    fi

    merged=$(merged_issues)
    for number in $merged; do
        closes+="Closes #$number"$'\n'
    done

    for number in $(gh api "repos/{owner}/{repo}/issues/$spec_issue/sub_issues?per_page=100" \
        --jq '.[] | select(.state == "open") | .number'); do
        if ! grep -qx "$number" <<<"$merged"; then
            remaining+=" #$number"
        fi
    done

    if [[ -n "$remaining" ]]; then
        spec_link="Part of"
        log "Spec #$spec_issue still has sub-issues not merged:$remaining"
    fi

    body="$spec_link #$spec_issue
$closes
Each sub-issue was merged into \`$base_branch\` with \`--no-ff\`. Merge this with a merge commit, not squash, to keep them grouped by issue.

🤖 Generated with [Claude Code](https://claude.com/claude-code)"

    pr_url=$(gh pr list --head "$base_branch" --base main --state open --json url --jq '.[0].url // ""')
    if [[ -n "$pr_url" ]]; then
        gh pr edit "$pr_url" --body "$body" >/dev/null
        log "Updated $pr_url"
        return
    fi

    title=$(gh issue view "$spec_issue" --json title --jq .title)
    gh pr create --base main --head "$base_branch" --title "feat: $title (#$spec_issue)" --body "$body"
}

work_issue() {
    local number=$1 branch="feat/issue-$1" session="ralph-issue-$1" outcome

    update_base_branch
    if [[ -n "$spec_issue" && -n "$(git ls-remote --heads origin "$branch")" ]]; then
        die "#$number: $branch is already on origin, delete it to retry"
    fi

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
        die "#$number: finished but the working tree is dirty, stopping"
    fi

    if [[ -n "$spec_issue" ]]; then
        merge_into_spec "$number" "$branch"
        sync_checkout
    elif [[ "$merge_prs" == true ]]; then
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
            --spec)
                [[ "${2:-}" =~ ^[0-9]+$ ]] || die "--spec needs an issue number"
                spec_issue=$2
                base_branch="spec/issue-$2"
                shift 2
                ;;
            *) die "Unknown option: $1" ;;
        esac
    done

    if [[ -n "$spec_issue" && "$merge_prs" == true ]]; then
        die "--merge has no effect with --spec, which already merges into its branch"
    fi

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

    if [[ -n "$spec_issue" ]]; then
        ensure_spec_branch
    fi

    while ((worked < max_issues)); do
        mapfile -t ready < <(ready_issues)
        if ((${#ready[@]} == 0)); then
            log "No ready issues left"
            if [[ -n "$spec_issue" ]]; then
                open_spec_pr
            fi
            break
        fi

        work_issue "${ready[0]}"
        worked=$((worked + 1))
    done

    git switch --quiet main
    log "Finished: $worked issue(s) worked"
}

main "$@"
