<?php

namespace Tests\Feature\Design;

use App\Enums\DisplayDecimals;
use App\Models\Currency;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** F1.2: core Blade components render the right elements, states and ARIA (component doc §1–§4). */
class ComponentsTest extends TestCase
{
    private function render(string $blade, array $data = [], ?array $errors = null): string
    {
        $bag = new ViewErrorBag;
        if ($errors !== null) {
            $bag->put('default', new MessageBag($errors));
        }
        // Components get $errors as shared view data, as the session middleware provides it.
        view()->share('errors', $bag);

        // Conditional attributes leave extra spaces; compare markup with whitespace collapsed.
        return (string) preg_replace('/\s+/', ' ', Blade::render($blade, $data));
    }

    // ---- Foundations ------------------------------------------------------------------------

    public function test_button_variants_link_icon_only_and_loading(): void
    {
        $html = $this->render('<x-ui.button>Save</x-ui.button>');
        $this->assertStringContainsString('<button type="button"', $html);
        $this->assertStringContainsString('bg-primary text-primary-fg', $html);

        $this->assertStringContainsString('<a href="/post"', $this->render('<x-ui.button href="/post" variant="secondary">Post</x-ui.button>'));

        $iconOnly = $this->render('<x-ui.button icon="heart" label="Save ad" />');
        $this->assertStringContainsString('aria-label="Save ad"', $iconOnly);
        $this->assertStringContainsString('size-10', $iconOnly);

        $loading = $this->render('<x-ui.button type="submit" :loading="true">Sending</x-ui.button>');
        $this->assertStringContainsString('aria-busy="true"', $loading);
        $this->assertStringContainsString('disabled', $loading);
        $this->assertStringContainsString('animate-spin', $loading);
        $this->assertStringContainsString('Sending', $loading); // text kept
    }

    public function test_badge_alert_chip_and_states_carry_text_not_just_colour(): void
    {
        $this->assertStringContainsString('Pending review', $this->render('<x-ui.badge variant="warning">Pending review</x-ui.badge>'));

        $danger = $this->render('<x-ui.alert variant="danger" title="Could not save">Try again.</x-ui.alert>');
        $this->assertStringContainsString('role="alert"', $danger);
        $this->assertStringContainsString('Could not save', $danger);
        $this->assertStringContainsString('role="status"', $this->render('<x-ui.alert>Saved.</x-ui.alert>'));

        $chip = $this->render('<x-ui.chip variant="filter" href="/x">Toyota</x-ui.chip>');
        $this->assertStringContainsString('aria-label="Remove filter: Toyota"', $chip);
        $this->assertStringContainsString('aria-pressed="true"', $this->render('<x-ui.chip variant="choice" :selected="true">Used</x-ui.chip>'));
    }

    public function test_skeleton_spinner_avatar_divider_empty_state(): void
    {
        $this->assertStringContainsString('aria-hidden="true"', $this->render('<x-ui.skeleton variant="card" />'));
        $this->assertStringContainsString('motion-reduce:animate-none', $this->render('<x-ui.spinner />'));

        $avatar = $this->render('<x-ui.avatar name="Nimal Perera" size="32" />');
        $this->assertStringContainsString('aria-label="Nimal Perera"', $avatar);
        $this->assertStringContainsString('>NP</span>', $avatar);
        $this->assertStringNotContainsString('style=', $avatar); // CSP: no inline styles

        $this->assertStringContainsString('role="separator"', $this->render('<x-ui.divider label="or" />'));

        $empty = $this->render('<x-ui.empty-state title="No ads yet">Post your first ad.<x-slot:action><x-ui.button href="/post">Post</x-ui.button></x-slot:action></x-ui.empty-state>');
        $this->assertStringContainsString('No ads yet', $empty);
        $this->assertStringContainsString('href="/post"', $empty);
    }

    public function test_flag_and_money(): void
    {
        $flag = $this->render('<x-ui.flag code="LK" label="Sri Lanka" />');
        $this->assertStringContainsString('fi fi-lk', $flag);
        $this->assertStringContainsString('aria-label="Sri Lanka"', $flag);
        $this->assertStringContainsString('aria-hidden="true"', $this->render('<x-ui.flag code="gb" />'));

        $currency = new Currency(['code' => 'QAA', 'minor_units' => 2]);
        $currency->forceFill(['code' => 'QAA', 'minor_units' => 2, 'symbol_override' => 'Q$', 'display_decimals' => DisplayDecimals::Auto]);
        $money = $this->render('<x-ui.money amount="7850000" :currency="$c" locale="en_US">/ month</x-ui.money>', ['c' => $currency]);
        $this->assertStringContainsString('Q$7,850,000', $money); // full figure, never 7.85M
        $this->assertStringContainsString('tabular', $money);
        $this->assertStringContainsString('/ month', $money);
    }

    // ---- Forms ------------------------------------------------------------------------------

    public function test_input_links_label_hint_and_error(): void
    {
        $html = $this->render('<x-ui.input label="Title" name="title" hint="Make and model" required />', [], ['title' => ['The title is required.']]);

        $this->assertStringContainsString('<label for="f-title"', $html);
        $this->assertStringContainsString('id="f-title"', $html);
        $this->assertStringContainsString('aria-describedby="f-title-hint f-title-error"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('id="f-title-error"', $html);
        $this->assertStringContainsString('The title is required.', $html);
        $this->assertStringContainsString('(required)', $html);
    }

    public function test_input_takes_its_name_from_wire_model_and_supports_prefix_and_password(): void
    {
        $html = $this->render('<x-ui.input label="Price" wire:model="price" prefix="Rs" inputmode="numeric" />', [], ['price' => ['Too high.']]);
        $this->assertStringContainsString('id="f-price"', $html);
        $this->assertStringContainsString('wire:model="price"', $html);
        $this->assertStringContainsString('Too high.', $html);
        $this->assertStringContainsString('>Rs</span>', $html);

        $password = $this->render('<x-ui.input label="Password" name="password" type="password" />');
        $this->assertStringContainsString('data-password-toggle', $password);
        $this->assertStringContainsString('aria-controls="f-password"', $password);
        $this->assertStringContainsString('aria-label="Show password"', $password);
    }

    public function test_textarea_select_checkbox_radio_switch(): void
    {
        $textarea = $this->render('<x-ui.textarea label="Description" name="description" maxlength="5000">Hello</x-ui.textarea>');
        $this->assertStringContainsString('data-counter="f-description-count"', $textarea);
        $this->assertStringContainsString('5 / 5000', $textarea);

        $select = $this->render('<x-ui.select label="Fuel" name="fuel" :options="[\'petrol\' => \'Petrol\', \'hybrid\' => \'Hybrid\']" selected="hybrid" placeholder="Any" />');
        $this->assertStringContainsString('<option value="">Any</option>', $select);
        $this->assertStringContainsString('<option value="hybrid" selected', $select);

        $checkbox = $this->render('<x-ui.checkbox label="Show my phone" name="show_call" :checked="true" />');
        $this->assertStringContainsString('type="checkbox"', $checkbox);
        $this->assertStringContainsString('checked', $checkbox);
        $this->assertStringContainsString('<label for="f-show_call"', $checkbox);

        $radios = $this->render('<x-ui.radio label="New" name="condition" value="new" /><x-ui.radio label="Used" name="condition" value="used" />');
        $this->assertStringContainsString('id="f-condition-new"', $radios);
        $this->assertStringContainsString('id="f-condition-used"', $radios);

        $this->assertStringContainsString('role="switch"', $this->render('<x-ui.switch label="WhatsApp" name="show_whatsapp" />'));
    }

    public function test_combobox_follows_the_aria_pattern(): void
    {
        $html = $this->render('<x-ui.combobox label="Model" wire:model="model" :options="[\'1\' => \'Aqua\', \'2\' => \'Axio\']" selected="2" />');

        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls="f-model-list"', $html);
        $this->assertStringContainsString('role="listbox"', $html);
        $this->assertStringContainsString('value="Axio"', $html);
        $this->assertMatchesRegularExpression('/type="hidden" data-combobox-value value="2"[^>]*wire:model="model"/', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
    }

    public function test_error_summary_links_to_fields(): void
    {
        $html = $this->render('<x-ui.form-errors />', [], ['title' => ['The title is required.'], 'price' => ['Too high.']]);

        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('data-error-summary', $html);
        $this->assertStringContainsString('There are 2 problems with this form', $html);
        $this->assertStringContainsString('href="#f-title"', $html);
        $this->assertSame('', trim($this->render('<x-ui.form-errors />')));
    }

    // ---- Navigation and overlays --------------------------------------------------------------

    public function test_modal_drawer_dropdown_and_toasts(): void
    {
        $modal = $this->render('<x-ui.modal id="report" title="Report this ad" :open="true">Why?<x-slot:footer>OK</x-slot:footer></x-ui.modal>');
        $this->assertStringContainsString('<dialog id="report" aria-labelledby="report-title" data-open', $modal);
        $this->assertStringContainsString('data-modal-close', $modal);
        $this->assertStringContainsString('aria-label="Close"', $modal);

        $this->assertStringContainsString('pb-[env(safe-area-inset-bottom)]', $this->render('<x-ui.drawer id="sort" title="Sort" side="bottom">x</x-ui.drawer>'));

        $menu = $this->render('<x-ui.dropdown label="Account" icon="circle-user" :icon-only="true"><x-ui.dropdown-item href="/me">My ads</x-ui.dropdown-item></x-ui.dropdown>');
        $this->assertStringContainsString('<details', $menu);
        $this->assertStringContainsString('aria-label="Account"', $menu);
        $this->assertStringContainsString('href="/me"', $menu);

        session()->flash('status', 'Saved.');
        $toasts = $this->render('<x-ui.toasts />');
        $this->assertStringContainsString('aria-live="polite"', $toasts);
        $this->assertStringContainsString('Saved.', $toasts);
        $this->assertStringContainsString('data-toast-template', $toasts);
    }

    public function test_tabs_and_pagination(): void
    {
        $tabs = $this->render('<x-ui.tabs label="My ads" active="active" :items="[\'active\' => [\'label\' => \'Active\', \'href\' => \'/a\', \'count\' => 3], \'draft\' => [\'label\' => \'Drafts\', \'href\' => \'/d\']]" />');
        $this->assertStringContainsString('aria-label="My ads"', $tabs);
        $this->assertMatchesRegularExpression('/href="\/a" aria-current="page"/', $tabs);
        $this->assertStringContainsString('>3</span>', $tabs);

        $wire = $this->render('<x-ui.tabs wire-model="status" active="open" :items="[\'open\' => \'Open\', \'closed\' => \'Closed\']" />');
        $this->assertStringContainsString("wire:click=\"\$set('status', 'closed')\"", $wire);

        $paginator = new LengthAwarePaginator(range(1, 24), 300, 24, 5, ['path' => '/ads']);
        $pages = $this->render('<x-ui.pagination :paginator="$p" />', ['p' => $paginator]);
        $this->assertStringContainsString('Page 5 of 13', $pages);
        $this->assertStringContainsString('aria-current="page"', $pages);
        $this->assertStringContainsString('rel="prev"', $pages);
        $this->assertStringContainsString('href="/ads?page=13"', $pages);

        $livewire = $this->render('<x-ui.pagination :paginator="$p" :livewire="true" />', ['p' => $paginator]);
        $this->assertStringContainsString("wire:click=\"nextPage('page')\"", $livewire);
        $this->assertSame('', trim($this->render('<x-ui.pagination :paginator="$p" />', ['p' => new LengthAwarePaginator([], 0, 24)])));
    }
}
