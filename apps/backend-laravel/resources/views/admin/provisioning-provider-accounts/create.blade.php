@extends('layouts.admin', ['title' => 'Create Provider Account'])
@section('content')
<x-page-header
    title="Create Provider Account"
    eyebrow="Integrations"
>
    <x-slot:actions>
        <a class="button secondary button-soft" href="/admin/provisioning-provider-accounts">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2Z"/></svg>
            <span>Back to Provider Accounts</span>
        </a>
    </x-slot:actions>
</x-page-header>

<div class="panel product-form-panel">
    <form class="product-form-shell provider-account-form" method="POST" action="/admin/provisioning-provider-accounts">
        @include('admin.provisioning-provider-accounts._form')
    </form>
</div>
@endsection
