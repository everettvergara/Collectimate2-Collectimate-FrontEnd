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
        navigate({ page: event.pageIndex + 1 });
    }

    function onSort(event) {
        navigate({
            sort: event.id,
            direction: event.desc ? 'desc' : 'asc',
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
