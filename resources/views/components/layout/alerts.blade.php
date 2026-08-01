<div class="layout-alerts-container" style="margin-bottom: 20px;">
    @if(session('success'))
        <x-ui.alert variant="accent" dismissable>
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="accent-2" dismissable>
            {{ session('error') }}
        </x-ui.alert>
    @endif

    @if(session('warning'))
        <x-ui.alert variant="warning" dismissable>
            {{ session('warning') }}
        </x-ui.alert>
    @endif

    @if(session('status'))
        <x-ui.alert variant="accent" dismissable>
            {{ session('status') }}
        </x-ui.alert>
    @endif

    @if($errors->any())
        <x-ui.alert variant="accent-2" dismissable>
            <ul style="margin: 0; padding-left: 18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif
</div>
