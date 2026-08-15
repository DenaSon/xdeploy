<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Documentation\Articles\Create as ArticleCreate;
use App\Livewire\Admin\Documentation\Articles\Edit as ArticleEdit;
use App\Livewire\Admin\Documentation\Categories\Create as CategoryCreate;
use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_open_documentation_management(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('admin.documentation.articles.index'))
            ->assertForbidden();
    }

    public function test_admin_can_open_documentation_management_routes(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.documentation.articles.index'))
            ->assertOk()
            ->assertSee('مستندات');

        $this->actingAs($admin)
            ->get(route('admin.documentation.categories.index'))
            ->assertOk()
            ->assertSee('دسته‌بندی مستندات');
    }

    public function test_admin_can_create_category_and_published_article(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CategoryCreate::class)
            ->set('title', 'شروع کار')
            ->set('slug', 'getting-started')
            ->set('description', 'راهنماهای اولیه')
            ->set('sortOrder', 10)
            ->set('isPublished', true)
            ->call('save')
            ->assertHasNoErrors();

        $category = DocumentationCategory::query()->sole();

        Livewire::test(ArticleCreate::class)
            ->set('categoryId', (string) $category->id)
            ->set('title', 'اتصال اولین سرور')
            ->set('slug', 'connect-first-server')
            ->set('excerpt', 'شروع سریع')
            ->set('content', "## اتصال\n\nمراحل اتصال سرور")
            ->set('sortOrder', 20)
            ->set('isPublished', true)
            ->call('save')
            ->assertHasNoErrors();

        $article = DocumentationArticle::query()->sole();

        self::assertTrue($article->is_published);
        self::assertNotNull($article->published_at);
        self::assertSame($category->id, $article->category_id);
    }

    public function test_published_article_requires_content(): void
    {
        $this->actingAs($this->admin());

        $category = DocumentationCategory::query()->create([
            'title' => 'سرورها',
            'slug' => 'servers',
            'sort_order' => 10,
            'is_published' => true,
        ]);

        Livewire::test(ArticleCreate::class)
            ->set('categoryId', (string) $category->id)
            ->set('title', 'افزودن سرور')
            ->set('slug', 'add-server')
            ->set('isPublished', true)
            ->call('save')
            ->assertHasErrors(['content' => 'required']);

        self::assertSame(0, DocumentationArticle::query()->count());
    }

    public function test_unpublishing_article_clears_publish_time(): void
    {
        $this->actingAs($this->admin());

        $category = DocumentationCategory::query()->create([
            'title' => 'سرورها',
            'slug' => 'servers',
            'sort_order' => 10,
            'is_published' => true,
        ]);
        $article = DocumentationArticle::query()->create([
            'category_id' => $category->id,
            'title' => 'افزودن سرور',
            'slug' => 'add-server',
            'content' => 'متن راهنما',
            'sort_order' => 10,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        Livewire::test(ArticleEdit::class, ['article' => $article])
            ->set('isPublished', false)
            ->call('save')
            ->assertHasNoErrors();

        $article->refresh();

        self::assertFalse($article->is_published);
        self::assertNull($article->published_at);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        return $admin;
    }
}
