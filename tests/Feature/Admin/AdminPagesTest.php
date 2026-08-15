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

    public function test_admin_can_create_a_draft_page(): void
    {
        $this->actingAs($this->admin());

        $component = Livewire::test(Create::class)
            ->set('title', 'حریم خصوصی')
            ->set('slug', 'privacy-policy')
            ->set('content', '')
            ->set('isPublished', false)
            ->call('save')
            ->assertHasNoErrors();

        $page = Page::query()->firstOrFail();

        $component->assertRedirect(route('admin.pages.edit', $page));

        $this->assertSame('حریم خصوصی', $page->title);
        $this->assertSame('privacy-policy', $page->slug);
        $this->assertFalse($page->is_published);
        $this->assertNull($page->published_at);
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

    public function test_admin_can_publish_and_unpublish_page(): void
    {
        $page = $this->page();

        $this->actingAs($this->admin());

        Livewire::test(Edit::class, ['page' => $page])
            ->set('content', "## قوانین\n\nمتن صفحه")
            ->set('isPublished', true)
            ->call('save')
            ->assertHasNoErrors();

        $page->refresh();

        $this->assertTrue($page->is_published);
        $this->assertNotNull($page->published_at);

        Livewire::test(Edit::class, ['page' => $page])
            ->set('isPublished', false)
            ->call('save')
            ->assertHasNoErrors();

        $page->refresh();

        $this->assertFalse($page->is_published);
        $this->assertNull($page->published_at);
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
        ]);
    }
}
