<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SettingsLayout from '@/layouts/settings/Layout.vue';
</script>

<template>
  <SettingsLayout>
    <div class="space-y-6">
      <h2 class="text-2xl font-bold mb-4">App Settings</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Device List</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="mb-2 text-muted-foreground">Manage and view all registered devices in your application.</p>
            <Link href="/devices" class="text-primary underline">Go to Device List</Link>
          </CardContent>
        </Card>
        <!-- Add more settings sections/cards here as needed -->
      </div>
    </div>
  </SettingsLayout>
</template>
