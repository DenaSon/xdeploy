<div class="drawer lg:drawer-open">

    <input
        id="panel-drawer"
        type="checkbox"
        class="drawer-toggle"
    >

    <div
        class="
            drawer-content
            flex
            min-h-screen
            flex-col
        "
    >

        {{ $slot }}

    </div>

    <div class="drawer-side">

        <label
            for="panel-drawer"
            class="drawer-overlay"
            aria-label="Close sidebar"
        ></label>

        @persist('panel-sidebar')

        <x-panel.sidebar />

        @endpersist

    </div>

</div>
