@extends('layouts.wizard')

@section('step_title', __('posting.steps.'.$step))

@section('step')
    {{-- Steps 3–6 are built in the next parts of F4.1. --}}
    <x-ui.empty-state icon="clock" :title="__('posting.pending.title')">
        {{ __('posting.pending.text') }}
        <x-slot:action><x-ui.button variant="secondary" :href="route('account.listings', ['status' => 'drafts'])">{{ __('layout.my_ads') }}</x-ui.button></x-slot:action>
    </x-ui.empty-state>
@endsection
