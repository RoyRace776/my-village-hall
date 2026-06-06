<?php

declare(strict_types=1);

namespace MYVH\Pages\Services;

use MYVH\Pages\Contracts\PageDefinitionProviderInterface;
use MYVH\Pages\DTO\PageDefinition;
use MYVH\Pages\Infrastructure\WordPressPageRepository;
use WP_Post;

class PageSyncService {
    public function __construct(
        private PageDefinitionProviderInterface $provider,
        private WordPressPageRepository $repository,
        private \MYVH\Pages\Content\PageContentBuilderInterface $contentBuilder
    ) {
    }

    public function syncAll(): void {
        if (! $this->shouldRun()) {
            return;
        }

        foreach ($this->provider->getPages() as $definition) {
            if (! $definition instanceof PageDefinition) {
                continue;
            }

            $this->sync($definition);
        }
    }

    private function sync(PageDefinition $def): void {
        $content = $this->contentBuilder->build($def);
        $page = $this->repository->findBySlug($def->slug);

        if ($page === null) {
            $page_id = $this->create($def, $content);
            if ($def->is_front_page) {
                update_option('show_on_front', 'page');
                update_option('page_on_front', $page_id);
            }
            return;
        }

        $this->updateIfNeeded($page, $def, $content);
        $this->repository->markManaged((int) $page->ID);
    }

    private function create(PageDefinition $def, string $content): int {
        $id = $this->repository->create([
            'post_type' => 'page',
            'post_title' => $def->title,
            'post_name' => $def->slug,
            'post_content' => $content,
            'post_status' => 'publish',
        ]);

        $this->repository->markManaged($id);
        return $id;
    }

    private function updateIfNeeded(WP_Post $page, PageDefinition $def, string $content): void {
        $needs_update =
            (string) $page->post_title !== $def->title
            || trim((string) $page->post_content) !== trim($content)
            || (string) $page->post_status !== 'publish';

        if (! $needs_update) {
            return;
        }

        $this->repository->update((int) $page->ID, [
            'post_title' => $def->title,
            'post_name' => $def->slug,
            'post_content' => $content,
            'post_status' => 'publish',
        ]);
    }

    private function shouldRun(): bool {
        if (! is_multisite()) {
            return true;
        }

        if (! function_exists('get_current_blog_id') || ! function_exists('get_main_site_id')) {
            return true;
        }

        return (int) get_current_blog_id() !== (int) get_main_site_id();
    }
}
