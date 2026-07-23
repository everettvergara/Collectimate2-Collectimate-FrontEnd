import { router } from '@inertiajs/vue3';

export function useListingNavigation(filters = {}, routeName = null) {
    function navigate(overrides = {}) {
        const name = routeName ?? route().current();
        router.get(
            route(name),
            { ...filters, ...overrides },
            { preserveState: true, replace: true },
        );
    }

    function onPage(event) {
        navigate({ page: event.page + 1 });
    }

    function onSort(event) {
        navigate({
            sort: event.sortField,
            direction: event.sortOrder === 1 ? 'asc' : 'desc',
            page: 1,
        });
    }

    function onSearch(search) {
        navigate({ search: search || undefined, page: 1 });
    }

    function onClear() {
        router.get(route(routeName ?? route().current()), {}, { preserveState: true, replace: true });
    }

    return { navigate, onPage, onSort, onSearch, onClear };
}
