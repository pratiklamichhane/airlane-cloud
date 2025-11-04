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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import {
    ArrowRight,
    Copy,
    FileText,
    Folder as FolderIcon,
    NotebookPen,
    Pin,
    Share2,
    Star,
    Trash2,
    UploadCloud,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, toRefs, watch } from 'vue';
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
    content?: string | null;
}

type StoragePermissionLevel = 'viewer' | 'editor' | 'owner';

interface StorageItemShareUser {
    id: number;
    name?: string | null;
    email: string;
}

interface StorageItemSharePermission {
    id: number;
    permission: StoragePermissionLevel;
    expires_at?: string | null;
    user: StorageItemShareUser | null;
}

interface StorageItemPublicLink {
    id: number;
    permission: StoragePermissionLevel;
    max_views?: number | null;
    view_count: number;
    expires_at?: string | null;
    token: string;
    url: string;
}

interface StorageItemSharing {
    public_link: StorageItemPublicLink | null;
    permissions: StorageItemSharePermission[];
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
    sharing?: StorageItemSharing;
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
const shareFormHandlers = ref<{ reset?: () => void; clearErrors?: () => void }>({});

const closeUploadSheet = () => closeSheet(uploadSheetOpen, uploadFormHandlers);
const closeFolderSheet = () => closeSheet(folderSheetOpen, folderFormHandlers);
const closeNoteSheet = () => closeSheet(noteSheetOpen, noteFormHandlers);

const previewOpen = ref(false);
const previewItem = ref<StorageItemResource | null>(null);

const previewUrl = computed(() => {
    if (!previewItem.value) {
        return null;
    }

    return itemUrl(previewItem.value);
});

const previewNoteContent = computed(() => {
    if (!previewItem.value || !previewItem.value.is_note) {
        return null;
    }

    return previewItem.value.latest_version?.content ?? null;
});

const previewMeta = computed(() => {
    if (!previewItem.value) {
        return null;
    }

    const item = previewItem.value;
    const parts: string[] = [typeLabelFor(item), formatBytes(item.size_bytes), `Updated ${formatDate(item.updated_at)}`];
    const version = versionLabelFor(item);


    if (version) {
        parts.push(version);
    }

    return parts.join(' • ');
});

const shareOpen = ref(false);
const shareItem = ref<StorageItemResource | null>(null);
const shareCopyState = ref<'idle' | 'copied'>('idle');

let shareCopyTimeout: ReturnType<typeof setTimeout> | null = null;

const sharePermissions = computed(() => shareItem.value?.sharing?.permissions ?? []);
const publicLink = computed(() => shareItem.value?.sharing?.public_link ?? null);

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

watch(previewOpen, (isOpen) => {
    if (!isOpen) {
        previewItem.value = null;
    }
});

watch(shareOpen, (isOpen) => {
    if (!isOpen) {
        shareFormHandlers.value.reset?.();
        shareFormHandlers.value.clearErrors?.();
        shareItem.value = null;
        shareCopyState.value = 'idle';

        if (shareCopyTimeout) {
            clearTimeout(shareCopyTimeout);
            shareCopyTimeout = null;
        }
    }
});

watch(publicLink, () => {
    if (shareCopyTimeout) {
        clearTimeout(shareCopyTimeout);
        shareCopyTimeout = null;
    }

    shareCopyState.value = 'idle';
});

function openPreview(item: StorageItemResource): void {
    previewItem.value = item;
    previewOpen.value = true;
}

function handlePreviewClick(event: MouseEvent, item: StorageItemResource): void {
    if (event.defaultPrevented) {
        return;
    }

    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    openPreview(item);
}

function openShare(item: StorageItemResource): void {
    shareItem.value = item;
    shareOpen.value = true;
    shareCopyState.value = 'idle';
}

const permissionLabels: Record<StoragePermissionLevel, string> = {
    viewer: 'Can view',
    editor: 'Can edit',
    owner: 'Owner',
};

const sharePermissionOptions = [
    { value: 'viewer' as const, label: permissionLabels.viewer },
    { value: 'editor' as const, label: permissionLabels.editor },
];

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

watch(paginatedItems, (itemList) => {
    if (!shareItem.value) {
        return;
    }

    const updated = itemList.find((candidate) => candidate.id === shareItem.value?.id);

    if (updated) {
        shareItem.value = updated;
    } else {
        shareOpen.value = false;
        shareItem.value = null;
    }
});

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

function formatPermissionLabel(level: StoragePermissionLevel): string {
    return permissionLabels[level] ?? level;
}

function formatDateTimeLocal(value?: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const pad = (input: number) => input.toString().padStart(2, '0');

    const year = date.getFullYear();
    const month = pad(date.getMonth() + 1);
    const day = pad(date.getDate());
    const hours = pad(date.getHours());
    const minutes = pad(date.getMinutes());

    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function shareExpiresLabel(value?: string | null): string {
    if (!value) {
        return 'No expiry';
    }

    return `Expires ${formatDate(value)}`;
}

function shareViewCountLabel(link: StorageItemPublicLink): string {
    if (link.max_views === null || link.max_views === undefined) {
        return `${link.view_count} views`;
    }

    return `${link.view_count} of ${link.max_views} views`;
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

function registerShareHandlers(reset: () => void, clearErrors: () => void): true {
    shareFormHandlers.value = { reset, clearErrors };

    return true;
}

function itemUrl(item: StorageItemResource): string | null {
    if (item.is_folder) {
        return storage.index({ query: { folder: item.id } }).url;
    }

    if (item.is_file || item.is_note) {
        return storage.items.show({ storageItem: item.id }).url;
    }

    return null;
}

async function copyPublicLink(): Promise<void> {
    const link = publicLink.value?.url;

    if (!link || !(navigator.clipboard && navigator.clipboard.writeText)) {
        return;
    }

    try {
        await navigator.clipboard.writeText(link);
        shareCopyState.value = 'copied';

        if (shareCopyTimeout) {
            clearTimeout(shareCopyTimeout);
        }

        shareCopyTimeout = setTimeout(() => {
            shareCopyState.value = 'idle';
            shareCopyTimeout = null;
        }, 2000);
    } catch (error) {
        console.error('Unable to copy link', error);
    }
}

onBeforeUnmount(() => {
    if (shareCopyTimeout) {
        clearTimeout(shareCopyTimeout);
        shareCopyTimeout = null;
    }
});

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
                                        <template v-if="item.is_folder && itemUrl(item)">
                                            <Link
                                                :href="itemUrl(item) as string"
                                                preserve-scroll
                                                class="truncate text-sm font-medium text-primary underline-offset-4 hover:underline"
                                                :title="item.name"
                                            >
                                                {{ item.name }}
                                            </Link>
                                        </template>
                                        <template v-else-if="itemUrl(item)">
                                            <a
                                                :href="itemUrl(item) as string"
                                                class="truncate text-sm font-medium text-primary underline-offset-4 hover:underline"
                                                :title="item.name"
                                                @click="handlePreviewClick($event, item)"
                                            >
                                                {{ item.name }}
                                            </a>
                                        </template>
                                        <span
                                            v-else
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
                                    v-if="item.is_folder && itemUrl(item)"
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="gap-1"
                                >
                                    <Link
                                        :href="itemUrl(item) as string"
                                        preserve-scroll
                                    >
                                        Open
                                        <ArrowRight class="size-3.5" />
                                    </Link>
                                </Button>
                                <Button
                                    v-if="(item.is_file || item.is_note) && itemUrl(item)"
                                    variant="ghost"
                                    size="sm"
                                    class="gap-1"
                                    type="button"
                                    @click="openShare(item)"
                                >
                                    Share
                                    <Share2 class="size-3.5" />
                                </Button>
                                <Button
                                    v-if="(item.is_file || item.is_note) && itemUrl(item)"
                                    variant="ghost"
                                    size="sm"
                                    class="gap-1"
                                    type="button"
                                    @click="openPreview(item)"
                                >
                                    View
                                    <ArrowRight class="size-3.5" />
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

    <Dialog v-model:open="shareOpen">
            <DialogContent class="w-full max-w-3xl overflow-hidden sm:max-w-4xl">
                <DialogHeader class="gap-1.5">
                    <DialogTitle>
                        Share {{ shareItem?.name ?? 'item' }}
                    </DialogTitle>
                    <DialogDescription>
                        Choose who can view or edit this {{ shareItem?.is_note ? 'note' : 'file' }}.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="shareItem" class="flex flex-col gap-6">
                    <section class="space-y-4 rounded-lg border border-border/60 bg-muted/30 p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="space-y-1">
                                <h3 class="text-sm font-medium text-foreground">Public link</h3>
                                <p class="text-xs text-muted-foreground">
                                    Generate a link that anyone can use to access this item.
                                </p>
                            </div>
                            <Button
                                v-if="publicLink"
                                variant="outline"
                                size="sm"
                                class="gap-2"
                                type="button"
                                :disabled="shareCopyState === 'copied'"
                                @click="copyPublicLink"
                            >
                                <Copy class="size-3.5" />
                                {{ shareCopyState === 'copied' ? 'Copied' : 'Copy link' }}
                            </Button>
                        </div>

                        <Form
                            v-bind="publicLink
                                ? storage.items.share.link.update.form({ storageItem: shareItem.id, storageShareLink: publicLink.id })
                                : storage.items.share.link.store.form({ storageItem: shareItem.id })"
                            :options="{ preserveScroll: true }"
                            reset-on-success
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="grid gap-1">
                                    <Label for="share-link-permission">Permission</Label>
                                    <select
                                        id="share-link-permission"
                                        name="permission"
                                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30 h-10 w-full rounded-md border bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow]"
                                        :value="publicLink?.permission ?? 'viewer'"
                                    >
                                        <option
                                            v-for="option in sharePermissionOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <InputError :message="errors.permission" />
                                </div>
                                <div class="grid gap-1">
                                    <Label for="share-link-max-views">View limit</Label>
                                    <Input
                                        id="share-link-max-views"
                                        name="max_views"
                                        type="number"
                                        min="1"
                                        :value="publicLink?.max_views ?? ''"
                                        placeholder="Unlimited"
                                    />
                                    <InputError :message="errors.max_views" />
                                </div>
                                <div class="grid gap-1">
                                    <Label for="share-link-expires">Expires</Label>
                                    <Input
                                        id="share-link-expires"
                                        name="expires_at"
                                        type="datetime-local"
                                        :value="formatDateTimeLocal(publicLink?.expires_at ?? null)"
                                    />
                                    <InputError :message="errors.expires_at" />
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 pt-3">
                                <Button type="submit" :disabled="processing">
                                    {{ publicLink ? 'Update link' : 'Create link' }}
                                </Button>
                            </div>
                        </Form>

                        <div
                            v-if="publicLink"
                            class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span>{{ formatPermissionLabel(publicLink.permission) }}</span>
                            <span aria-hidden="true">•</span>
                            <span>{{ shareExpiresLabel(publicLink.expires_at) }}</span>
                            <span aria-hidden="true">•</span>
                            <span>{{ shareViewCountLabel(publicLink) }}</span>
                        </div>

                        <Form
                            v-if="publicLink"
                            v-bind="storage.items.share.link.destroy.form({ storageItem: shareItem.id, storageShareLink: publicLink.id })"
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="destructive"
                                size="sm"
                                :disabled="processing"
                                class="gap-2"
                            >
                                <Trash2 class="size-3.5" />
                                Disable link
                            </Button>
                        </Form>
                    </section>

                    <section class="space-y-4">
                        <div class="space-y-1">
                            <h3 class="text-sm font-medium text-foreground">Share with people</h3>
                            <p class="text-xs text-muted-foreground">
                                Invite specific teammates by email and control their access level.
                            </p>
                        </div>

                        <Form
                            v-bind="storage.items.share.permissions.store.form({ storageItem: shareItem.id })"
                            :options="{ preserveScroll: true }"
                            reset-on-success
                            v-slot="{ errors, processing, reset, clearErrors }"
                        >
                            <template v-if="registerShareHandlers(reset, clearErrors)" />
                            <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                                <div class="grid gap-1">
                                    <Label for="share-email">Email</Label>
                                    <Input
                                        id="share-email"
                                        name="email"
                                        type="email"
                                        required
                                        autocomplete="email"
                                        placeholder="collaborator@example.com"
                                    />
                                    <InputError :message="errors.email" />
                                </div>
                                <div class="grid gap-1">
                                    <Label for="share-permission">Permission</Label>
                                    <select
                                        id="share-permission"
                                        name="permission"
                                        class="border-input focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30 h-10 w-full rounded-md border bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow]"
                                    >
                                        <option
                                            v-for="option in sharePermissionOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <InputError :message="errors.permission" />
                                </div>
                                <div class="grid gap-1">
                                    <Label for="share-expires">Expires</Label>
                                    <Input id="share-expires" name="expires_at" type="datetime-local" />
                                    <InputError :message="errors.expires_at" />
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 pt-3">
                                <Button type="submit" :disabled="processing">
                                    Share
                                </Button>
                            </div>
                        </Form>

                        <div v-if="sharePermissions.length" class="space-y-3">
                            <div
                                v-for="permission in sharePermissions"
                                :key="permission.id"
                                class="rounded-lg border border-border/60 bg-background p-4 shadow-xs"
                            >
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="space-y-1">
                                        <div class="text-sm font-medium text-foreground">
                                            {{ permission.user?.name ?? permission.user?.email ?? 'Shared user' }}
                                        </div>
                                        <div class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                            <span>{{ permission.user?.email ?? 'Unknown user' }}</span>
                                            <span aria-hidden="true">•</span>
                                            <span>{{ formatPermissionLabel(permission.permission) }}</span>
                                            <span aria-hidden="true">•</span>
                                            <span>{{ shareExpiresLabel(permission.expires_at) }}</span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                                        <Form
                                            v-bind="storage.items.share.permissions.update.form({
                                                storageItem: shareItem.id,
                                                storageItemPermission: permission.id,
                                            })"
                                            :options="{ preserveScroll: true }"
                                            reset-on-success
                                            class="flex flex-col gap-2 sm:flex-row sm:items-center"
                                            v-slot="{ errors, processing }"
                                        >
                                            <div class="flex flex-wrap items-center gap-2">
                                                <select
                                                    aria-label="Permission"
                                                    name="permission"
                                                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30 h-9 rounded-md border bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow]"
                                                    :value="permission.permission"
                                                >
                                                    <option
                                                        v-for="option in sharePermissionOptions"
                                                        :key="`${permission.id}-${option.value}`"
                                                        :value="option.value"
                                                    >
                                                        {{ option.label }}
                                                    </option>
                                                </select>
                                                <Input
                                                    aria-label="Expiry"
                                                    name="expires_at"
                                                    type="datetime-local"
                                                    :value="formatDateTimeLocal(permission.expires_at ?? null)"
                                                />
                                                <Button type="submit" size="sm" :disabled="processing">
                                                    Save
                                                </Button>
                                            </div>
                                            <div class="flex flex-col gap-1 text-xs text-destructive">
                                                <InputError :message="errors.permission" />
                                                <InputError :message="errors.expires_at" />
                                            </div>
                                        </Form>

                                        <Form
                                            v-bind="storage.items.share.permissions.destroy.form({
                                                storageItem: shareItem.id,
                                                storageItemPermission: permission.id,
                                            })"
                                            :options="{ preserveScroll: true }"
                                            v-slot="{ processing }"
                                        >
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                size="sm"
                                                class="gap-2 text-destructive hover:text-destructive"
                                                :disabled="processing"
                                            >
                                                <Trash2 class="size-3.5" />
                                                Remove
                                            </Button>
                                        </Form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-else class="text-xs text-muted-foreground">
                            No one else has access yet.
                        </p>
                    </section>
                </div>

                <DialogFooter class="flex justify-end border-t border-border/50 pt-4">
                    <DialogClose as-child>
                        <Button type="button">
                            Close
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    <Dialog v-model:open="previewOpen">
            <DialogContent class="w-full max-w-4xl overflow-hidden sm:max-w-5xl">
                <DialogHeader class="gap-1.5">
                    <DialogTitle>{{ previewItem?.name ?? 'Preview' }}</DialogTitle>
                    <DialogDescription v-if="previewMeta">
                        {{ previewMeta }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="previewItem" class="flex flex-col gap-4">
                    <div v-if="previewItem.is_note" class="flex flex-col gap-3">
                        <pre
                            v-if="previewNoteContent !== null"
                            class="max-h-[60vh] overflow-y-auto whitespace-pre-wrap break-words rounded-lg border border-border bg-muted/40 p-4 text-left text-sm text-foreground"
                        >{{ previewNoteContent }}</pre>
                        <p v-else class="text-sm text-muted-foreground">
                            This note does not have any content yet.
                        </p>
                    </div>

                    <div v-else-if="previewUrl" class="flex flex-col gap-3">
                        <iframe
                            :key="previewItem.id"
                            :src="previewUrl"
                            title="File preview"
                            class="h-[60vh] w-full rounded-lg border border-border bg-background"
                            loading="lazy"
                            allowfullscreen
                        />
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        We could not generate a preview for this item. Use the download option instead.
                    </p>
                </div>

                <DialogFooter class="flex flex-col gap-3 border-t border-border/50 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <div v-if="previewItem" class="text-xs text-muted-foreground">
                        Created {{ formatDate(previewItem.created_at) }}
                    </div>

                    <div class="flex flex-wrap gap-2 sm:justify-end">
                        <Button
                            v-if="previewUrl"
                            variant="outline"
                            as-child
                            class="gap-2"
                        >
                            <a :href="previewUrl" target="_blank" rel="noopener noreferrer">
                                Open in new tab
                            </a>
                        </Button>
                        <DialogClose as-child>
                            <Button type="button">
                                Close
                            </Button>
                        </DialogClose>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
