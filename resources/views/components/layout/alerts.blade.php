<div class="layout-alerts-container mb-4">
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
            <ul class="m-0 ps-4">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif
</div>

{{--
    Container único de toasts do Bootstrap (bootstrap-conventions §9).
    O id `notification-container` é contrato: `NotificationService` injeta os
    `.toast` aqui e a suíte Dusk o asserta.
--}}
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="notification-container"></div>
