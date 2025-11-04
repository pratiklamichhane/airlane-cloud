<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Form, Head } from '@inertiajs/vue3';
import adminTeams from '@/routes/admin/teams';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import InputError from '@/components/InputError.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

interface TeamMemberSummary {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface TeamSummary {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    member_count: number;
    members: TeamMemberSummary[];
}

interface PageProps {
    teams: TeamSummary[];
}

const props = defineProps<PageProps>();
</script>

<template>
    <Head title="Team Management" />

    <AppLayout :breadcrumbs="[{ title: 'Admin', href: adminTeams.index.url() }, { title: 'Teams', href: adminTeams.index.url() }]">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Team management</h1>
                <p class="text-sm text-muted-foreground">
                    Create teams and manage their members. Additional options will appear here over time.
                </p>
            </div>

            <Card class="max-w-3xl">
                <CardHeader>
                    <CardTitle>Create a new team</CardTitle>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="adminTeams.store.form()"
                        :options="{ preserveScroll: true }"
                        reset-on-success
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-4">
                            <div class="grid gap-2">
                                <Label for="team-name">Team name</Label>
                                <Input id="team-name" name="name" type="text" required placeholder="Design" />
                                <InputError :message="errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="team-description">Description</Label>
                                <Textarea
                                    id="team-description"
                                    name="description"
                                    rows="3"
                                    placeholder="Add some context about this team."
                                />
                                <InputError :message="errors.description" />
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <Button type="submit" :disabled="processing">
                                Create team
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>

            <div class="space-y-6">
                <Card
                    v-for="team in props.teams"
                    :key="team.id"
                >
                    <CardHeader class="gap-2">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle>{{ team.name }}</CardTitle>
                                <p class="text-sm text-muted-foreground">
                                    {{ team.description || 'No description provided yet.' }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ team.member_count }} {{ team.member_count === 1 ? 'member' : 'members' }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <h3 class="text-sm font-medium text-foreground">Add a member</h3>
                            <Form
                                v-bind="adminTeams.members.store.form({ team: team.id })"
                                :options="{ preserveScroll: true }"
                                reset-on-success
                                v-slot="{ errors, processing }"
                            >
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div class="grid gap-1">
                                        <Label :for="`member-email-${team.id}`">Email</Label>
                                        <Input :id="`member-email-${team.id}`" name="email" type="email" required placeholder="member@example.com" />
                                        <InputError :message="errors.email" />
                                    </div>
                                    <div class="grid gap-1">
                                        <Label :for="`member-name-${team.id}`">Name (optional)</Label>
                                        <Input :id="`member-name-${team.id}`" name="name" type="text" placeholder="Alex Johnson" />
                                        <InputError :message="errors.name" />
                                    </div>
                                    <div class="grid gap-1">
                                        <Label :for="`member-role-${team.id}`">Role</Label>
                                        <Input :id="`member-role-${team.id}`" name="role" type="text" placeholder="member" />
                                        <InputError :message="errors.role" />
                                    </div>
                                </div>
                                <div class="flex justify-end pt-3">
                                    <Button type="submit" :disabled="processing">
                                        Add member
                                    </Button>
                                </div>
                            </Form>
                        </div>

                        <div class="space-y-2">
                            <h3 class="text-sm font-medium text-foreground">
                                Members
                            </h3>
                            <div v-if="team.members.length" class="divide-y divide-border rounded-md border border-border/60">
                                <div
                                    v-for="member in team.members"
                                    :key="member.id"
                                    class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <div class="text-sm font-medium text-foreground">{{ member.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ member.email }}</div>
                                        <div class="text-xs text-muted-foreground">Role: {{ member.role }}</div>
                                    </div>
                                    <Form
                                        v-bind="adminTeams.members.destroy.form({ team: team.id, user: member.id })"
                                        :options="{ preserveScroll: true }"
                                        v-slot="{ processing }"
                                    >
                                        <Button type="submit" variant="ghost" class="text-destructive hover:text-destructive" :disabled="processing">
                                            Remove
                                        </Button>
                                    </Form>
                                </div>
                            </div>
                            <p v-else class="text-xs text-muted-foreground">
                                This team does not have any members yet.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
