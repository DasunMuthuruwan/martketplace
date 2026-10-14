<div class="space-y-10">
    <div>
        <h1 class="text-h1 font-semibold">{{ __('admin.nav.design_system') }}</h1>
        <p class="mt-1 text-ui text-fg-muted">{{ __('admin.design.intro') }}</p>
    </div>

    {{-- Colours --}}
    <section class="space-y-3">
        <h2 class="text-h2 font-semibold">{{ __('admin.design.colours') }}</h2>
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
            @foreach ($tokens as $token)
                <li class="overflow-hidden rounded-card border border-line">
                    <div class="h-12 bg-{{ $token }}"></div>
                    <p class="bg-surface px-2 py-1 font-mono text-micro text-fg-muted">{{ $token }}</p>
                </li>
            @endforeach
        </ul>
        {{-- Tailwind sees the full class names here (the loop above builds them). --}}
        <span class="hidden bg-canvas bg-surface bg-surface-muted bg-line bg-control bg-fg bg-fg-muted bg-fg-subtle bg-primary bg-success bg-warning bg-danger bg-info"></span>
    </section>

    {{-- Typography --}}
    <section class="space-y-2">
        <h2 class="text-h2 font-semibold">{{ __('admin.design.typography') }}</h2>
        <p class="text-display font-[650]">Display 32</p>
        <p class="text-h1 font-[650]">Heading 1 · 26</p>
        <p class="text-h2 font-semibold">Heading 2 · 20</p>
        <p class="text-h3 font-semibold">Heading 3 · 16</p>
        <p class="text-body">Body 15 — The quick brown fox jumps over the lazy dog.</p>
        <p class="text-ui">UI 14 — Buttons, inputs, menus</p>
        <p class="text-small text-fg-subtle">Small 13 — Meta, hints</p>
        <p class="font-mono text-small">Geist Mono · ID 1234</p>
    </section>

    {{-- Buttons and badges --}}
    <section class="space-y-3">
        <h2 class="text-h2 font-semibold">{{ __('admin.design.buttons') }}</h2>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button>Primary</x-ui.button>
            <x-ui.button variant="secondary">Secondary</x-ui.button>
            <x-ui.button variant="ghost">Ghost</x-ui.button>
            <x-ui.button variant="danger">Danger</x-ui.button>
            <x-ui.button variant="link">Link</x-ui.button>
            <x-ui.button icon="plus">With icon</x-ui.button>
            <x-ui.button icon="heart" label="Save" variant="secondary" />
            <x-ui.button :loading="true">Saving</x-ui.button>
            <x-ui.button disabled>Disabled</x-ui.button>
            <x-ui.button size="sm" variant="secondary">Small</x-ui.button>
            <x-ui.button size="lg">Large</x-ui.button>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge>Neutral</x-ui.badge>
            <x-ui.badge variant="success" icon="circle-check">Approved</x-ui.badge>
            <x-ui.badge variant="warning">Pending review</x-ui.badge>
            <x-ui.badge variant="danger">Rejected</x-ui.badge>
            <x-ui.badge variant="info">New</x-ui.badge>
            <x-ui.badge variant="outline">Sold</x-ui.badge>
            <x-ui.chip>Static</x-ui.chip>
            <x-ui.chip variant="filter">Toyota</x-ui.chip>
            <x-ui.chip variant="choice" :selected="true">Used</x-ui.chip>
            <x-ui.chip variant="choice">New</x-ui.chip>
        </div>
    </section>

    {{-- Prices and flags --}}
    <section class="space-y-3">
        <h2 class="text-h2 font-semibold">{{ __('admin.design.prices') }}</h2>
        <p><x-ui.money amount="7850000" :currency="$currency" :country="$country" locale="en" /></p>
        <p><x-ui.money amount="85000" :currency="$currency" :country="$country" locale="en">/ month</x-ui.money></p>
        <p><x-ui.money amount="12500000" :currency="$currency" :country="$country" locale="en" size="price-xl" /></p>
        <p class="flex gap-2 text-h2">@foreach ($flags as $flagCountry)<x-ui.flag :code="$flagCountry->iso2" :label="$flagCountry->name" />@endforeach</p>
    </section>

    {{-- Alerts and states --}}
    <section class="space-y-3">
        <h2 class="text-h2 font-semibold">{{ __('admin.design.feedback') }}</h2>
        <x-ui.alert title="Information">Prices in this country are shown in the listing's currency.</x-ui.alert>
        <x-ui.alert variant="success">Your ad was submitted for review.</x-ui.alert>
        <x-ui.alert variant="warning" title="Expiring soon">Renew within 3 days to keep it live.</x-ui.alert>
        <x-ui.alert variant="danger" title="Could not save">Check the highlighted fields.</x-ui.alert>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4"><x-ui.skeleton variant="card" /><x-ui.skeleton variant="card" /></div>
        <x-ui.empty-state title="No ads match" icon="search">Try fewer filters or another location.<x-slot:action><x-ui.button variant="secondary">Clear filters</x-ui.button></x-slot:action></x-ui.empty-state>
        <div class="flex items-center gap-3"><x-ui.avatar name="Nimal Perera" size="40" /><x-ui.avatar name="A" size="32" /><x-ui.spinner /></div>
        <x-ui.button wire:click="toast" variant="secondary">{{ __('admin.design.show_toast') }}</x-ui.button>
    </section>

    {{-- Forms --}}
    <section class="max-w-xl space-y-4">
        <h2 class="text-h2 font-semibold">{{ __('admin.design.forms') }}</h2>
        <x-ui.input label="Title" name="demo_title" hint="Make, model and year" required />
        <x-ui.input label="Price" name="demo_price" prefix="{{ $currency->code }}" inputmode="numeric" error="Enter a price." />
        <x-ui.input label="Mileage" name="demo_mileage" suffix="km" inputmode="numeric" />
        <x-ui.input label="Password" name="demo_password" type="password" />
        <x-ui.textarea label="Description" name="demo_description" maxlength="5000" />
        <x-ui.select label="Fuel" name="demo_fuel" placeholder="Any" :options="['petrol' => 'Petrol', 'diesel' => 'Diesel', 'hybrid' => 'Hybrid']" />
        <x-ui.combobox label="Model" name="demo_model" placeholder="Type to search" :options="['1' => 'Aqua', '2' => 'Axio', '3' => 'Corolla', '4' => 'Prius', '5' => 'Vitz']" />
        <x-ui.checkbox label="I agree to the terms" name="demo_terms" />
        <div class="flex gap-4"><x-ui.radio label="New" name="demo_condition" value="new" /><x-ui.radio label="Used" name="demo_condition" value="used" :checked="true" /></div>
        <x-ui.switch label="Show WhatsApp button" name="demo_whatsapp" :checked="true" />
    </section>

    {{-- Navigation and overlays --}}
    <section class="space-y-4">
        <h2 class="text-h2 font-semibold">{{ __('admin.design.navigation') }}</h2>
        <x-ui.tabs label="Demo" active="active" :items="['active' => ['label' => 'Active', 'href' => '#', 'count' => 12], 'pending' => ['label' => 'Pending', 'href' => '#', 'count' => 2], 'drafts' => ['label' => 'Drafts', 'href' => '#']]" />
        <x-ui.pagination :paginator="$paginator" />
        <div class="flex flex-wrap gap-2">
            <x-ui.button variant="secondary" data-modal-open="demo-modal">{{ __('admin.design.open_modal') }}</x-ui.button>
            <x-ui.button variant="secondary" data-modal-open="demo-sheet">{{ __('admin.design.open_sheet') }}</x-ui.button>
            <x-ui.dropdown label="Sort: Newest" align="left">
                <x-ui.dropdown-item href="#">Newest</x-ui.dropdown-item>
                <x-ui.dropdown-item href="#">Price: low to high</x-ui.dropdown-item>
                <x-ui.dropdown-item href="#" :danger="true" icon="trash-2">Delete</x-ui.dropdown-item>
            </x-ui.dropdown>
        </div>
        <x-ui.modal id="demo-modal" title="Report this ad">
            <p class="text-ui text-fg-muted">Tell us what is wrong with this ad.</p>
            <x-slot:footer><x-ui.button variant="secondary" data-modal-close>Cancel</x-ui.button><x-ui.button data-modal-close>Send report</x-ui.button></x-slot:footer>
        </x-ui.modal>
        <x-ui.drawer id="demo-sheet" title="Sort by" side="bottom">
            <div class="space-y-2"><x-ui.radio label="Newest" name="demo_sort" value="new" :checked="true" /><x-ui.radio label="Price: low to high" name="demo_sort" value="price" /></div>
        </x-ui.drawer>
    </section>

    {{-- Card grid --}}
    <section class="space-y-3">
        <h2 class="text-h2 font-semibold">{{ __('admin.design.grid') }}</h2>
        <div class="listing-grid-area">
            <ul class="listing-grid">
                @for ($i = 1; $i <= 8; $i++)
                    <li class="overflow-hidden rounded-card border border-line bg-surface">
                        <div class="grid aspect-[4/3] place-items-center bg-surface-muted text-fg-subtle"><x-ui.icon name="image" size="24" /></div>
                        <div class="space-y-1 p-3">
                            <p><x-ui.money amount="{{ 1250000 * $i }}" :currency="$currency" :country="$country" locale="en" /></p>
                            <p class="line-clamp-2 text-ui text-fg">Sample listing {{ $i }}</p>
                            <p class="text-small text-fg-subtle">Sample place · 2h ago</p>
                        </div>
                    </li>
                @endfor
            </ul>
        </div>
    </section>
</div>
