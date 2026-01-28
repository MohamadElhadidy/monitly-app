@php
    $user = auth()->user();
@endphp

<nav class="flex-1 overflow-y-auto px-4 py-4 space-y-6">
    {{-- Workspace --}}
    <div class="space-y-1">
        <div class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Workspace</div>

        <x-nav.link
            :href="route('dashboard')"
            :active="request()->routeIs('dashboard')"
            :icon="'
                <svg xmlns=&quot;http://www.w3.org/2000/svg&quot; class=&quot;h-5 w-5&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot;>
                    <path d=&quot;M3 13h8V3H3z&quot;/><path d=&quot;M13 21h8V11h-8z&quot;/><path d=&quot;M13 3h8v6h-8z&quot;/><path d=&quot;M3 21h8v-6H3z&quot;/>
                </svg>
            '"
        >
            Overview
        </x-nav.link>

        @if (Route::has('monitors.index'))
            <x-nav.link
                :href="route('monitors.index')"
                :active="request()->routeIs('monitors.*')"
                :icon="'
                    <svg xmlns=&quot;http://www.w3.org/2000/svg&quot; class=&quot;h-5 w-5&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot;>
                        <path d=&quot;M4 4h16v16H4z&quot;/><path d=&quot;M8 12h8&quot;/><path d=&quot;M12 8v8&quot;/>
                    </svg>
                '"
            >
                Monitors
            </x-nav.link>
        @endif

        @if (Route::has('status.public'))
            <x-nav.link
                :href="route('status.public')"
                :active="request()->routeIs('status.*')"
                :icon="'
                    <svg xmlns=&quot;http://www.w3.org/2000/svg&quot; class=&quot;h-5 w-5&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot;>
                        <path d=&quot;M3 12h3l3-9 6 18 3-9h3&quot;/>
                    </svg>
                '"
            >
                Status
            </x-nav.link>
        @endif

        @if (Route::has('billing.show'))
            <x-nav.link
                :href="route('billing.show')"
                :active="request()->routeIs('billing.*')"
                :icon="'
                    <svg xmlns=&quot;http://www.w3.org/2000/svg&quot; class=&quot;h-5 w-5&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot;>
                        <path d=&quot;M21 8H3&quot;/><path d=&quot;M21 12H3&quot;/><path d=&quot;M21 16H3&quot;/><path d=&quot;M7 8v12&quot;/>
                    </svg>
                '"
            >
                Billing
            </x-nav.link>
        @endif
    </div>

    {{-- Admin --}}
    @if ($user && $user->can('access-admin') && Route::has('admin.index'))
        <div class="space-y-1">
            <div class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Admin</div>

            <x-nav.link :href="route('admin.index')" :active="request()->routeIs('admin.index')">Dashboard</x-nav.link>
            @if (Route::has('admin.users')) <x-nav.link :href="route('admin.users')" :active="request()->routeIs('admin.users*')">Users</x-nav.link> @endif
            @if (Route::has('admin.teams')) <x-nav.link :href="route('admin.teams')" :active="request()->routeIs('admin.teams*')">Teams</x-nav.link> @endif
            @if (Route::has('admin.monitors')) <x-nav.link :href="route('admin.monitors')" :active="request()->routeIs('admin.monitors*')">Monitors</x-nav.link> @endif
            @if (Route::has('admin.subscriptions')) <x-nav.link :href="route('admin.subscriptions')" :active="request()->routeIs('admin.subscriptions*')">Subscriptions</x-nav.link> @endif
            @if (Route::has('admin.audit_logs')) <x-nav.link :href="route('admin.audit_logs')" :active="request()->routeIs('admin.audit_logs*')">Audit logs</x-nav.link> @endif
            @if (Route::has('admin.system')) <x-nav.link :href="route('admin.system')" :active="request()->routeIs('admin.system*')">System</x-nav.link> @endif
        </div>
    @endif
</nav>