@props(['title' => null])
{{-- Error summary at the top of long forms after a failed submit (component doc §2). It takes focus
     (resources/js/ui/forms.js) and each message links to its field. --}}
@if (isset($errors) && $errors->any())
    <div role="alert" tabindex="-1" data-error-summary {{ $attributes->class('rounded-card border border-danger bg-surface p-4') }}>
        <p class="flex items-center gap-2 font-semibold text-danger">
            <x-ui.icon name="circle-alert" size="20" />{{ $title ?? trans_choice('ui.form_errors', $errors->count(), ['count' => $errors->count()]) }}
        </p>
        <ul class="mt-2 list-disc space-y-1 pl-9 text-ui">
            @foreach ($errors->getMessages() as $key => $messages)
                <li><a class="text-fg underline underline-offset-2" href="#f-{{ preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $key) }}">{{ $messages[0] }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
