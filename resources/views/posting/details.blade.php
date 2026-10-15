@extends('layouts.wizard')

@section('step_title', __('posting.details.title'))
@section('step_intro', __('posting.details.intro', ['category' => $category->name]))

@section('step')
    @php
        $type = old('type', $listing->type?->value ?? ($types[0]->value ?? null));
        $condition = old('condition', $listing->condition?->value);
        $groups = collect($fields)->groupBy(fn ($f) => $f['group'] ?? '');
    @endphp

    <x-ui.form-errors class="mb-4" />

    <form method="POST" action="{{ route('listings.update', [$listing, 'details']) }}" novalidate class="space-y-6">
        @csrf

        @if (count($types) > 1)
            <fieldset>
                <legend class="mb-2 text-ui font-medium text-fg">{{ __('posting.details.type') }}<span aria-hidden="true" class="text-danger"> *</span></legend>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($types as $t)
                        <label class="flex cursor-pointer items-center justify-center rounded-card border border-line p-3 text-ui font-medium text-fg has-[:checked]:border-primary has-[:checked]:bg-surface-muted">
                            <input type="radio" name="type" value="{{ $t->value }}" @checked($type === $t->value) class="sr-only" data-listing-type>
                            {{ __('public.types.'.$t->value) }}
                        </label>
                    @endforeach
                </div>
                @error('type')<p class="mt-1 text-small text-danger">{{ $message }}</p>@enderror
            </fieldset>
        @else
            <input type="hidden" name="type" value="{{ $types[0]->value ?? '' }}">
        @endif

        <x-ui.input :label="__('posting.details.ad_title')" name="title" :value="old('title', $listing->title)" required
            minlength="{{ \App\Actions\Listings\SaveListingDetails::TITLE_MIN }}" maxlength="{{ \App\Actions\Listings\SaveListingDetails::TITLE_MAX }}" :hint="__('posting.details.title_hint')" />

        @if ($category->show_condition)
            <fieldset data-condition-group>
                <legend class="mb-2 text-ui font-medium text-fg">{{ __('public.condition') }}</legend>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($conditions as $c)
                        <label class="flex cursor-pointer items-center justify-center rounded-card border border-line p-3 text-center text-ui text-fg has-[:checked]:border-primary has-[:checked]:bg-surface-muted has-[:checked]:font-semibold">
                            <input type="radio" name="condition" value="{{ $c->value }}" @checked($condition === $c->value) class="sr-only">
                            {{ __('public.conditions.'.$c->value) }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-small text-fg-subtle">{{ __('posting.details.condition_hint') }}</p>
                @error('condition')<p class="mt-1 text-small text-danger">{{ $message }}</p>@enderror
            </fieldset>
        @endif

        @foreach ($groups as $group => $groupFields)
            <section class="space-y-4" @if ($group !== '') aria-labelledby="group-{{ \Illuminate\Support\Str::slug($group) }}" @endif>
                @if ($group !== '')<h2 id="group-{{ \Illuminate\Support\Str::slug($group) }}" class="border-b border-line pb-1 text-h3 font-semibold">{{ $group }}</h2>@endif
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($groupFields as $field)
                        <div @class(['sm:col-span-2' => in_array($field['type'], ['multiselect', 'text'], true) || $field['dimension'] === 'area'])>
                            @include('posting.partials.attribute-field', ['field' => $field])
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <x-ui.textarea :label="__('posting.details.description')" name="description" required rows="8"
            maxlength="{{ \App\Actions\Listings\SaveListingDetails::DESCRIPTION_MAX }}" :hint="__('posting.details.description_hint')">{{ old('description', $listing->description) }}</x-ui.textarea>

        @include('posting.partials.actions', ['back' => route('listings.edit', [$listing, 'location'])])
    </form>
@endsection
