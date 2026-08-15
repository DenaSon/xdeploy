<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Pages\Create;
use App\Livewire\Admin\Pages\Edit;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_page_management_routes(): void
    {
        $admin = $this->admin();
        $page = $this->page();

        $this->actingAs($admin)
            ->get(route('admin.pages.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.pages.create'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertSee($page->title);
    }

    public function test_admin_can_create_a_draft_page_with_footer_settings(): void
    {
        $this->actingAs($this->admin());

        $component = Livewire::test(Create::class)
            ->set('title', 'حریم خصوصی')
            ->set('slug', 'privacy-policy')
            ->set('content', '')
            ->set('isPublished', false)
            ->set('showInFooter', true)
            ->set('sortOrder', 20)
            ->call('save')
            ->assertHasNoErrors();

        $page = Page::query()->firstOrFail();

        $component->assertRedirect(route('admin.pages.edit', $page));

        $this->assertSame('حریم خصوصی', $page->title);
        $this->assertSame('privacy-policy', $page->slug);
        $this->assertFalse($page->is_published);
        $this->assertNull($page->published_at);
        $this->assertTrue($page->show_in_footer);
        $this->assertSame(20, $page->sort_order);
    }

    public function test_published_page_requires_content(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Create::class)
            ->set('title', 'شرایط استفاده')
            ->set('slug', 'terms-of-service')
            ->set('content', '')
            ->set('isPublished', true)
            ->call('save')
            ->assertHasErrors(['content' => 'required']);

        $this->assertDatabaseCount('pages', 0);
    }

    public function test_footer_sort_order_must_be_within_supported_range(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Create::class)
            ->set('title', 'قوانین')
            ->set('slug', 'rules')
            ->set('sortOrder', -1)
            ->call('save')
            ->assertHasErrors(['sortOrder' => 'min']);

        $this->assertDatabaseCount('pages', 0);
    }

    public function test_admin_can_publish_unpublish_and_update_footer_settings(): void
    {
        $page = $this->page();

        $this->actingAs($this->admin());

        Livewire::test(Edit::class, ['page' => $page])
            ->set('content', "## قوانین\n\nمتن صفحه")
            ->set('isPublished', true)
            ->set('showInFooter', true)
            ->set('sortOrder', 30)
            ->call('save')
            ->assertHasNoErrors();

        $page->refresh();

        $this->assertTrue($page->is_published);
        $this->assertNotNull($page->published_at);
        $this->assertTrue($page->show_in_footer);
        $this->assertSame(30, $page->sort_order);

        Livewire::test(Edit::class, ['page' => $page])
            ->set('isPublished', false)
            ->set('showInFooter', false)
            ->set('sortOrder', 5)
            ->call('save')
            ->assertHasNoErrors();

        $page->refresh();

        $this->assertFalse($page->is_published);
        $this->assertNull($page->published_at);
        $this->assertFalse($page->show_in_footer);
        $this->assertSame(5, $page->sort_order);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        return $admin;
    }

    private function page(): Page
    {
        return Page::query()->create([
            'title' => 'درباره کورفلر',
            'slug' => 'about',
            'content' => 'متن آزمایشی',
            'is_published' => false,
            'published_at' => null,
            'show_in_footer' => false,
            'sort_order' => 0,
        ]);
    }
}
