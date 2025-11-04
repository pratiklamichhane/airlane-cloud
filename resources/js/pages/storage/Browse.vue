<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Form, Head, Link } from '@inertiajs/vue3';
import storage from '@/routes/storage';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import {
    ArrowRight,
    FileText,
    Folder as FolderIcon,
    NotebookPen,
    Pin,
    Star,
    UploadCloud,
} from 'lucide-vue-next';
import { computed, ref, toRefs, watch } from 'vue';
import type { Ref } from 'vue';

interface StorageItemTag {
    id: number;
    name: string;
    color?: string | null;
}

interface StorageItemVersion {
    id: number;
    version: number;
    size_bytes: number;
    mime_type?: string | null;
    checksum?: string | null;
    created_at?: string | null;
}

interface StorageItemResource {
    id: number;
    type: string;
    name: string;
    slug: string;
    parent_id?: number | null;
    size_bytes: number;
    disk?: string | null;
    stored_path?: string | null;
    mime_type?: string | null;
    checksum?: string | null;
    metadata?: Record<string, unknown>;
    is_folder: boolean;
    is_file: boolean;
    is_note: boolean;
    is_pinned: boolean;
    is_favorite: boolean;
    latest_version?: StorageItemVersion | null;
    tags?: StorageItemTag[];
    created_at?: string | null;
    updated_at?: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Paginated<TItem> {
    data: TItem[];
    links?: Record<string, string | null>;
    meta?: PaginationMeta;
}

interface BreadcrumbItem {
    title: string;
    href: string;
}

interface AuthUser {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

interface AuthProps {
    user: AuthUser;
}

interface Summary {
    plan: {
        key: string;
        name: string;
    };
    usage: {
        used_bytes: number;
        max_bytes: number;
        remaining_bytes: number;
        percent: number;
    };
    limits: {
        max_file_bytes: number;
        version_cap: number;
        trash_retention_days: number;
    };
}

interface PageProps {
    filters: {
        folder?: number | null;
    };
    folder: StorageItemResource | null;
    items: Paginated<StorageItemResource>;
    breadcrumbs: BreadcrumbItem[];
    summary: Summary;
}

const props = defineProps<PageProps & {
    name: string;
    quote: { message: string; author: string };
    auth: AuthProps;
    sidebarOpen: boolean;
}>();
const { folder, items, summary, breadcrumbs } = toRefs(props);

const uploadSheetOpen = ref(false);
const folderSheetOpen = ref(false);
const noteSheetOpen = ref(false);

const uploadFormHandlers = ref<{ reset?: () => void; clearErrors?: () => void }>({});
const folderFormHandlers = ref<{ reset?: () => void; clearErrors?: () => void }>({});
const noteFormHandlers = ref<{ reset?: () => void; clearErrors?: () => void }>({});

const closeUploadSheet = () => closeSheet(uploadSheetOpen, uploadFormHandlers);
const closeFolderSheet = () => closeSheet(folderSheetOpen, folderFormHandlers);
const closeNoteSheet = () => closeSheet(noteSheetOpen, noteFormHandlers);

watch(uploadSheetOpen, (isOpen) => {
    if (!isOpen) {
        uploadFormHandlers.value.reset?.();
        uploadFormHandlers.value.clearErrors?.();
    }
});

watch(folderSheetOpen, (isOpen) => {
    if (!isOpen) {
        folderFormHandlers.value.reset?.();
        folderFormHandlers.value.clearErrors?.();
    }
});

watch(noteSheetOpen, (isOpen) => {
    if (!isOpen) {
        noteFormHandlers.value.reset?.();
        noteFormHandlers.value.clearErrors?.();
    }
});

const pageTitle = computed(() => folder.value?.name ?? 'All Files');
const headTitle = computed(() => (folder.value ? `${folder.value.name} · Storage` : 'Storage'));

const usagePercent = computed(() => {
    const percent = summary.value?.usage?.percent ?? 0;
    if (!Number.isFinite(percent)) {
        return 0;
    }

    return Math.min(100, Math.max(0, percent));
});

const usagePercentLabel = computed(() => `${Math.round(usagePercent.value)}%`);

const usageLabel = computed(
    () => `${formatBytes(summary.value?.usage?.used_bytes ?? 0)} of ${formatBytes(summary.value?.usage?.max_bytes ?? 0)}`,
);

const remainingLabel = computed(
    () => `${formatBytes(summary.value?.usage?.remaining_bytes ?? 0)} available`,
);

const maxStorageLabel = computed(() => formatBytes(summary.value?.usage?.max_bytes ?? 0));
const maxFileSizeLabel = computed(() => formatBytes(summary.value?.limits?.max_file_bytes ?? 0));
const versionCapLabel = computed(() => formatVersionCap(summary.value?.limits?.version_cap ?? 0));
const trashRetentionLabel = computed(
    () => formatTrashRetention(summary.value?.limits?.trash_retention_days ?? 0),
);

const paginatedItems = computed(() => items.value?.data ?? []);
const paginationLinks = computed(() => items.value?.meta?.links ?? []);
const totalItems = computed(() => items.value?.meta?.total ?? paginatedItems.value.length);
const hasItems = computed(() => paginatedItems.value.length > 0);

const emptyHeadline = computed(() => (folder.value ? 'This folder is empty' : 'Your storage is ready'));
const emptyBody = computed(() =>
    folder.value
        ? 'Use the actions above to add files, notes, or sub-folders.'
        : 'Upload a file, create a note, or make a folder to get started.',
);

const handleUploadSuccess = () => {
    uploadSheetOpen.value = false;
};

const handleFolderSuccess = () => {
    folderSheetOpen.value = false;
};

const handleNoteSuccess = () => {
    noteSheetOpen.value = false;
};

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
});

function formatBytes(value: number): string {
    if (!Number.isFinite(value) || value <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    const exponent = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1);
    const converted = value / Math.pow(1024, exponent);
    const precision = converted >= 100 || exponent === 0 ? 0 : 1;

    return `${converted.toFixed(precision)} ${units[exponent]}`;
}

function formatDate(value?: string | null): string {
    if (!value) {
        return 'Never';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Never';
    }

    return dateFormatter.format(date);
}

function typeLabelFor(item: StorageItemResource): string {
    if (item.is_folder) {
        return 'Folder';
    }

    if (item.is_note) {
        return 'Note';
    }

    return item.latest_version?.mime_type ?? item.mime_type ?? 'File';
}

function versionLabelFor(item: StorageItemResource): string | null {
    if (!item.latest_version) {
        return null;
    }

    return `v${item.latest_version.version}`;
}

function iconFor(item: StorageItemResource) {
    if (item.is_folder) {
        return FolderIcon;
    }

    if (item.is_note) {
        return NotebookPen;
    }

    return FileText;
}

function formatVersionCap(value: number): string {
    if (value <= 0) {
        return 'Unlimited versions';
    }

    return `${value} versions saved`;
}

function formatTrashRetention(days: number): string {
    if (days <= 0) {
        return 'Trash disabled';
    }

    if (days === 1) {
        return '1 day in trash';
    }

    return `${days} days in trash`;
}

function closeSheet(
    openRef: Ref<boolean>,
    handlersRef: Ref<{ reset?: () => void; clearErrors?: () => void }>,
) {
    handlersRef.value.reset?.();
    handlersRef.value.clearErrors?.();
    openRef.value = false;
}

function registerUploadHandlers(reset: () => void, clearErrors: () => void): true {
    uploadFormHandlers.value = { reset, clearErrors };

    return true;
}

function registerFolderHandlers(reset: () => void, clearErrors: () => void): true {
    folderFormHandlers.value = { reset, clearErrors };

    return true;
}

function registerNoteHandlers(reset: () => void, clearErrors: () => void): true {
    noteFormHandlers.value = { reset, clearErrors };

    return true;
}
</script>

<template>
    <Head :title="headTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">
                        {{ pageTitle }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Keep everything organised with quick access to uploads,
                        notes, and folders.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Sheet v-model:open="uploadSheetOpen">
                        <SheetTrigger as-child>
                            <Button class="gap-2">
                                <UploadCloud class="size-4" />
                                Upload file
                            </Button>
                        </SheetTrigger>

                        <SheetContent class="w-full sm:max-w-lg">
                            <SheetHeader class="gap-3">
                                <SheetTitle>Upload a file</SheetTitle>
                                <SheetDescription>
                                    Files larger than {{ maxFileSizeLabel }}
                                    will be rejected automatically.
                                </SheetDescription>
                            </SheetHeader>

                            <Form
                                v-bind="storage.upload.store.form()"
                                enctype="multipart/form-data"
                                :options="{ preserveScroll: true }"
                                reset-on-success
                                @success="handleUploadSuccess"
                                v-slot="{ errors, processing, progress, reset, clearErrors }"
                            >
                                <template
                                    v-if="registerUploadHandlers(reset, clearErrors)"
                                />

                                <div class="flex flex-col gap-4 py-2">
                                    <input
                                        v-if="folder?.id"
                                        type="hidden"
                                        name="parent_id"
                                        :value="folder.id"
                                    />

                                    <div class="grid gap-2">
                                        <Label for="file">File</Label>
                                        <input
                                            id="file"
                                            name="file"
                                            type="file"
                                            required
                                            class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-12 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-9 file:items-center file:rounded-md file:border-0 file:bg-primary file:px-3 file:text-sm file:font-medium file:text-primary-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive"
                                        />
                                        <InputError :message="errors.file" />
                                        <p class="text-xs text-muted-foreground">
                                            Maximum size: {{ maxFileSizeLabel }}.
                                        </p>
                                    </div>

                                    <div
                                        v-if="progress"
                                        class="h-2 w-full overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-primary transition-all"
                                            :style="{ width: `${progress.percentage}%` }"
                                        ></div>
                                    </div>
                                </div>

                                <SheetFooter class="gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        @click="closeUploadSheet()"
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" :disabled="processing">
                                        <Spinner
                                            v-if="processing"
                                            class="size-4"
                                        />
                                        Upload
                                    </Button>
                                </SheetFooter>
                            </Form>
                        </SheetContent>
                    </Sheet>

                    <Sheet v-model:open="folderSheetOpen">
                        <SheetTrigger as-child>
                            <Button variant="secondary" class="gap-2">
                                <FolderIcon class="size-4" />
                                New folder
                            </Button>
                        </SheetTrigger>

                        <SheetContent class="w-full sm:max-w-md">
                            <SheetHeader class="gap-3">
                                <SheetTitle>Create a folder</SheetTitle>
                                <SheetDescription>
                                    Folders help you organise files and notes by
                                    project or topic.
                                </SheetDescription>
                            </SheetHeader>

                            <Form
                                v-bind="storage.folders.store.form()"
                                :options="{ preserveScroll: true }"
                                reset-on-success
                                @success="handleFolderSuccess"
                                v-slot="{ errors, processing, reset, clearErrors }"
                            >
                                <template
                                    v-if="registerFolderHandlers(reset, clearErrors)"
                                />

                                <div class="flex flex-col gap-4 py-2">
                                    <input
                                        v-if="folder?.id"
                                        type="hidden"
                                        name="parent_id"
                                        :value="folder.id"
                                    />

                                    <div class="grid gap-2">
                                        <Label for="folder-name">Folder name</Label>
                                        <Input
                                            id="folder-name"
                                            name="name"
                                            required
                                            autocomplete="off"
                                            placeholder="Marketing assets"
                                        />
                                        <InputError :message="errors.name" />
                                    </div>
                                </div>

                                <SheetFooter class="gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        @click="closeFolderSheet()"
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" :disabled="processing">
                                        <Spinner
                                            v-if="processing"
                                            class="size-4"
                                        />
                                        Create folder
                                    </Button>
                                </SheetFooter>
                            </Form>
                        </SheetContent>
                    </Sheet>

                    <Sheet v-model:open="noteSheetOpen">
                        <SheetTrigger as-child>
                            <Button variant="outline" class="gap-2">
                                <NotebookPen class="size-4" />
                                New note
                            </Button>
                        </SheetTrigger>

                        <SheetContent class="w-full sm:max-w-md">
                            <SheetHeader class="gap-3">
                                <SheetTitle>Capture a note</SheetTitle>
                                <SheetDescription>
                                    Notes are perfect for quick references or
                                    meeting summaries alongside your files.
                                </SheetDescription>
                            </SheetHeader>

                            <Form
                                v-bind="storage.notes.store.form()"
                                :options="{ preserveScroll: true }"
                                reset-on-success
                                @success="handleNoteSuccess"
                                v-slot="{ errors, processing, reset, clearErrors }"
                            >
                                <template
                                    v-if="registerNoteHandlers(reset, clearErrors)"
                                />

                                <div class="flex flex-col gap-4 py-2">
                                    <input
                                        v-if="folder?.id"
                                        type="hidden"
                                        name="parent_id"
                                        :value="folder.id"
                                    />

                                    <div class="grid gap-2">
                                        <Label for="note-name">Title</Label>
                                        <Input
                                            id="note-name"
                                            name="name"
                                            required
                                            placeholder="Sprint retrospective"
                                        />
                                        <InputError :message="errors.name" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="note-content">Content</Label>
                                        <Textarea
                                            id="note-content"
                                            name="content"
                                            required
                                            rows="8"
                                            placeholder="Add quick details, meeting notes, or reminders."
                                        />
                                        <InputError :message="errors.content" />
                                    </div>
                                </div>

                                <SheetFooter class="gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        @click="closeNoteSheet()"
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" :disabled="processing">
                                        <Spinner
                                            v-if="processing"
                                            class="size-4"
                                        />
                                        Save note
                                    </Button>
                                </SheetFooter>
                            </Form>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Plan</CardTitle>
                        <CardDescription>Current subscription</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 pb-6">
                        <div class="flex flex-wrap items-center gap-3">
                            <Badge variant="secondary">
                                {{ summary.plan.name }}
                            </Badge>
                            <span
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                {{ summary.plan.key }}
                            </span>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Enjoy up to {{ maxStorageLabel }} of storage with
                            this plan.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Storage usage</CardTitle>
                        <CardDescription>
                            {{ usageLabel }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 pb-6">
                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                            <span>Usage</span>
                            <span class="font-medium text-foreground">
                                {{ usagePercentLabel }}
                            </span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{ width: `${usagePercent}%` }"
                            ></div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ remainingLabel }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Limits</CardTitle>
                        <CardDescription>Plan-specific restrictions</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 pb-6">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">Max file size</span>
                            <span class="font-medium text-foreground">
                                {{ maxFileSizeLabel }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">Version history</span>
                            <span class="font-medium text-foreground">
                                {{ versionCapLabel }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">Trash retention</span>
                            <span class="font-medium text-foreground">
                                {{ trashRetentionLabel }}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="gap-0">
                <CardHeader class="border-b">
                    <div class="flex flex-col gap-1">
                        <CardTitle>Files &amp; folders</CardTitle>
                        <CardDescription>
                            {{ folder ? `Items inside ${folder.name}` : 'Browse everything you have stored in Airlane.' }}
                        </CardDescription>
                    </div>
                    <div class="text-sm text-muted-foreground">
                        {{ totalItems }} item{{ totalItems === 1 ? '' : 's' }}
                    </div>
                </CardHeader>
                <CardContent class="px-0">
                    <div v-if="hasItems" class="divide-y">
                        <div
                            v-for="item in paginatedItems"
                            :key="item.id"
                            class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex min-w-0 items-start gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-border/60 bg-muted/50 text-muted-foreground"
                                >
                                    <component :is="iconFor(item)" class="size-5" />
                                </div>
                                <div class="min-w-0 space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="truncate text-sm font-medium text-foreground"
                                            :title="item.name"
                                        >
                                            {{ item.name }}
                                        </span>
                                        <Badge variant="outline">
                                            {{ typeLabelFor(item) }}
                                        </Badge>
                                        <Badge
                                            v-if="item.is_pinned"
                                            variant="secondary"
                                            class="gap-1"
                                        >
                                            <Pin class="size-3" />
                                            Pinned
                                        </Badge>
                                        <Badge
                                            v-if="item.is_favorite"
                                            variant="secondary"
                                            class="gap-1"
                                        >
                                            <Star class="size-3" />
                                            Favorite
                                        </Badge>
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground"
                                    >
                                        <span>{{ formatBytes(item.size_bytes) }}</span>
                                        <span aria-hidden="true">•</span>
                                        <span>Updated {{ formatDate(item.updated_at) }}</span>
                                        <template v-if="versionLabelFor(item)">
                                            <span aria-hidden="true">•</span>
                                            <span>{{ versionLabelFor(item) }}</span>
                                        </template>
                                    </div>
                                    <div
                                        v-if="item.tags && item.tags.length"
                                        class="flex flex-wrap gap-1.5"
                                    >
                                        <Badge
                                            v-for="tag in item.tags"
                                            :key="tag.id"
                                            variant="outline"
                                            class="bg-transparent"
                                            :style="
                                                tag.color
                                                    ? {
                                                        borderColor: tag.color,
                                                        color: tag.color,
                                                    }
                                                    : undefined
                                            "
                                        >
                                            {{ tag.name }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <Button
                                    v-if="item.is_folder"
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="gap-1"
                                >
                                    <Link
                                        :href="storage.index({ query: { folder: item.id } })"
                                        preserve-scroll
                                    >
                                        Open
                                        <ArrowRight class="size-3.5" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center"
                    >
                        <div class="text-lg font-medium text-foreground">
                            {{ emptyHeadline }}
                        </div>
                        <p class="max-w-md text-sm text-muted-foreground">
                            {{ emptyBody }}
                        </p>
                        <Button class="mt-2 gap-2" @click="uploadSheetOpen = true">
                            <UploadCloud class="size-4" />
                            Upload your first file
                        </Button>
                    </div>
                </CardContent>

                <CardContent
                    v-if="paginationLinks.length > 1"
                    class="border-t px-6 py-4"
                >
                    <nav class="flex flex-wrap items-center gap-2 text-sm">
                        <template
                            v-for="(link, index) in paginationLinks"
                            :key="index"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                v-html="link.label"
                                :class="[
                                    'inline-flex items-center rounded-md px-3 py-1.5 transition-colors',
                                    link.active
                                        ? 'bg-muted text-foreground'
                                        : 'text-muted-foreground hover:bg-muted/80',
                                ]"
                            />
                            <span
                                v-else
                                v-html="link.label"
                                class="inline-flex items-center rounded-md px-3 py-1.5 text-muted-foreground/70"
                            />
                        </template>
                    </nav>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
