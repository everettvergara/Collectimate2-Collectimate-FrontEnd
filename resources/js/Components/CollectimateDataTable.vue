<script setup>
import { computed } from 'vue';
import {
    FlexRender,
    getCoreRowModel,
    useVueTable,
} from '@tanstack/vue-table';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { ArrowDown, ArrowUp, ArrowUpDown } from '@lucide/vue';

const props = defineProps({
    value: {
        type: Array,
        default: () => [],
    },
    columns: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    paginator: {
        type: Boolean,
        default: true,
    },
    rows: {
        type: Number,
        default: 15,
    },
    totalRecords: {
        type: Number,
        default: 0,
    },
    first: {
        type: Number,
        default: 0,
    },
    sortField: {
        type: String,
        default: null,
    },
    sortOrder: {
        type: Number,
        default: null,
    },
    lazy: {
        type: Boolean,
        default: true,
    },
    selectable: {
        type: Boolean,
        default: false,
    },
    selectedIds: {
        type: Array,
        default: () => [],
    },
    rowKey: {
        type: String,
        default: 'id',
    },
    showRowNumbers: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['page', 'sort', 'update:selectedIds']);

const pageIndex = computed(() => Math.floor((props.first || 0) / (props.rows || 15)));
const pageCount = computed(() =>
    props.rows > 0 ? Math.max(1, Math.ceil((props.totalRecords || 0) / props.rows)) : 1,
);
const showingFrom = computed(() =>
    props.totalRecords === 0 ? 0 : (props.first || 0) + 1,
);
const showingTo = computed(() =>
    Math.min((props.first || 0) + (props.rows || 15), props.totalRecords || 0),
);

const sorting = computed(() => {
    if (!props.sortField || !props.sortOrder) {
        return [];
    }
    return [{ id: props.sortField, desc: props.sortOrder === -1 }];
});

const pageRowIds = computed(() =>
    (props.value ?? []).map((row) => row?.[props.rowKey]).filter((id) => id !== null && id !== undefined),
);

const selectedSet = computed(() => new Set((props.selectedIds ?? []).map((id) => String(id))));

const allPageSelected = computed(() => {
    if (pageRowIds.value.length === 0) return false;
    return pageRowIds.value.every((id) => selectedSet.value.has(String(id)));
});

const somePageSelected = computed(() => {
    if (pageRowIds.value.length === 0) return false;
    const selectedCount = pageRowIds.value.filter((id) => selectedSet.value.has(String(id))).length;
    return selectedCount > 0 && selectedCount < pageRowIds.value.length;
});

function rowId(row) {
    return row?.[props.rowKey];
}

function isSelected(row) {
    const id = rowId(row);
    if (id === null || id === undefined) return false;
    return selectedSet.value.has(String(id));
}

function setSelectedIds(ids) {
    emit('update:selectedIds', ids);
}

function toggleRow(row, checked) {
    const id = rowId(row);
    if (id === null || id === undefined) return;
    const key = String(id);
    const next = new Set(selectedSet.value);
    if (checked) {
        next.add(key);
    } else {
        next.delete(key);
    }
    // Preserve original types from page data where possible
    const byKey = new Map(pageRowIds.value.map((pageId) => [String(pageId), pageId]));
    setSelectedIds(
        Array.from(next).map((k) => (byKey.has(k) ? byKey.get(k) : Number.isNaN(Number(k)) ? k : Number(k))),
    );
}

function toggleAllPage(checked) {
    const next = new Set(selectedSet.value);
    if (checked) {
        pageRowIds.value.forEach((id) => next.add(String(id)));
    } else {
        pageRowIds.value.forEach((id) => next.delete(String(id)));
    }
    const byKey = new Map(pageRowIds.value.map((pageId) => [String(pageId), pageId]));
    const prior = (props.selectedIds ?? []).filter((id) => next.has(String(id)));
    const priorKeys = new Set(prior.map((id) => String(id)));
    const added = pageRowIds.value.filter((id) => next.has(String(id)) && !priorKeys.has(String(id)));
    setSelectedIds([...prior, ...added]);
}

function rowNumber(index) {
    return (props.first || 0) + index + 1;
}

const tableColumns = computed(() => {
    const cols = [];

    if (props.selectable) {
        cols.push({
            id: '_select',
            header: '_select',
            enableSorting: false,
            cell: () => null,
        });
    }

    if (props.showRowNumbers) {
        cols.push({
            id: '_row_number',
            header: 'No.',
            enableSorting: false,
            cell: () => null,
        });
    }

    (props.columns ?? []).forEach((col) => {
        const id = col.id ?? col.accessorKey;
        cols.push({
            id,
            accessorKey: col.accessorKey,
            header: col.header ?? id,
            enableSorting: !!col.sortable,
            cell: (info) => {
                if (col.accessorKey) {
                    return info.getValue();
                }
                return null;
            },
        });
    });

    return cols;
});

const table = useVueTable({
    get data() {
        return props.value ?? [];
    },
    get columns() {
        return tableColumns.value;
    },
    getCoreRowModel: getCoreRowModel(),
    manualSorting: true,
    manualPagination: true,
    get pageCount() {
        return pageCount.value;
    },
    state: {
        get sorting() {
            return sorting.value;
        },
    },
});

function toggleSort(column) {
    if (!column.getCanSort()) {
        return;
    }
    const id = column.id;
    const current = sorting.value[0];
    const desc = current?.id === id ? !current.desc : false;
    emit('sort', { id, desc });
}

function goPrev() {
    if (pageIndex.value <= 0) {
        return;
    }
    emit('page', { pageIndex: pageIndex.value - 1 });
}

function goNext() {
    if (pageIndex.value >= pageCount.value - 1) {
        return;
    }
    emit('page', { pageIndex: pageIndex.value + 1 });
}
</script>

<template>
    <div class="space-y-3">
        <div class="rounded border overflow-hidden" style="border-color: var(--color-border); background: var(--color-bg-surface)">
            <Table class="table-collectimate">
                <TableHeader>
                    <TableRow
                        v-for="headerGroup in table.getHeaderGroups()"
                        :key="headerGroup.id"
                        class="border-b"
                        style="border-color: var(--color-border)"
                    >
                        <TableHead
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                            class="form-label"
                            :class="{
                                'w-10': header.column.id === '_select',
                                'w-14': header.column.id === '_row_number',
                            }"
                        >
                            <template v-if="header.column.id === '_select'">
                                <Checkbox
                                    :model-value="allPageSelected"
                                    :class="somePageSelected && !allPageSelected ? 'opacity-70' : ''"
                                    aria-label="Select all on page"
                                    @update:model-value="toggleAllPage"
                                />
                            </template>
                            <button
                                v-else-if="header.column.getCanSort()"
                                type="button"
                                class="inline-flex items-center gap-1 uppercase tracking-wide"
                                @click="toggleSort(header.column)"
                            >
                                <FlexRender
                                    :render="header.column.columnDef.header"
                                    :props="header.getContext()"
                                />
                                <ArrowDown
                                    v-if="sorting[0]?.id === header.column.id && sorting[0]?.desc"
                                    class="h-3.5 w-3.5"
                                />
                                <ArrowUp
                                    v-else-if="sorting[0]?.id === header.column.id"
                                    class="h-3.5 w-3.5"
                                />
                                <ArrowUpDown v-else class="h-3.5 w-3.5 opacity-50" />
                            </button>
                            <template v-else>
                                <FlexRender
                                    :render="header.column.columnDef.header"
                                    :props="header.getContext()"
                                />
                            </template>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="table.getRowModel().rows?.length">
                        <TableRow
                            v-for="(row, rowIndex) in table.getRowModel().rows"
                            :key="row.id"
                            class="border-b"
                            style="border-color: var(--color-border)"
                        >
                            <TableCell
                                v-for="cell in row.getVisibleCells()"
                                :key="cell.id"
                                :class="{
                                    'w-10': cell.column.id === '_select',
                                    'w-14 tabular-nums': cell.column.id === '_row_number',
                                }"
                            >
                                <template v-if="cell.column.id === '_select'">
                                    <Checkbox
                                        :model-value="isSelected(row.original)"
                                        :aria-label="`Select account ${rowId(row.original)}`"
                                        @update:model-value="(checked) => toggleRow(row.original, checked)"
                                    />
                                </template>
                                <template v-else-if="cell.column.id === '_row_number'">
                                    {{ rowNumber(rowIndex) }}
                                </template>
                                <slot
                                    v-else-if="$slots[`cell.${cell.column.id}`]"
                                    :name="`cell.${cell.column.id}`"
                                    :row="row.original"
                                />
                                <FlexRender
                                    v-else
                                    :render="cell.column.columnDef.cell"
                                    :props="cell.getContext()"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell :colspan="Math.max(tableColumns.length, 1)" class="h-24 text-center">
                            <span style="color: var(--color-text-muted)">
                                {{ loading ? 'Loading…' : 'No records found.' }}
                            </span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="paginator"
            class="flex flex-wrap items-center justify-between gap-2 text-sm"
            style="color: var(--color-text-muted)"
        >
            <div>
                Showing {{ showingFrom }}–{{ showingTo }} of {{ totalRecords }}
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" :disabled="pageIndex <= 0" @click="goPrev">
                    Previous
                </Button>
                <span>Page {{ pageIndex + 1 }} of {{ pageCount }}</span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="pageIndex >= pageCount - 1 || totalRecords === 0"
                    @click="goNext"
                >
                    Next
                </Button>
            </div>
        </div>
    </div>
</template>
