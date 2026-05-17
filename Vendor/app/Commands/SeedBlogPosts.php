<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\AppDatabase;
use App\Libraries\SecurityCangService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Inserts sample published blog posts via Config\Database (AppDatabase).
 */
final class SeedBlogPosts extends BaseCommand
{
    protected $group = 'Database';

    protected $name = 'blog:seed';

    protected $description = 'Create sample published public blog posts using the application database connection.';

    public function run(array $params)
    {
        $db = AppDatabase::connection();

        if (! $db->tableExists('public_contents')) {
            CLI::error('Table public_contents does not exist. Run installation first.');

            return EXIT_ERROR;
        }

        $authorId = $this->resolveAuthorId($db);
        $security = new SecurityCangService();
        $now = date('Y-m-d H:i:s');
        $inserted = 0;
        $skipped = 0;

        foreach ($this->samplePosts() as $post) {
            $slug = (string) $post['slug'];
            $exists = $db->table('public_contents')->where('slug', $slug)->countAllResults();
            if ($exists > 0) {
                CLI::write("Skipped (exists): {$slug}", 'yellow');
                $skipped++;

                continue;
            }

            $publishedAt = (string) ($post['published_at'] ?? $now);

            $row = [
                'c_id'         => $security->generateFor(SecurityCangService::PUBLIC_CONTENT_URL_ID, 'public_contents'),
                'title'        => (string) $post['title'],
                'slug'         => $slug,
                'summary'      => (string) $post['summary'],
                'body'         => (string) $post['body'],
                'status'       => 'published',
                'show_in_nav'  => false,
                'nav_label'    => null,
                'nav_order'    => 0,
                'author_id'    => $authorId,
                'published_at' => $publishedAt,
                'created_at'   => $publishedAt,
                'updated_at'   => null,
            ];

            try {
                $db->table('public_contents')->insert($row);
                CLI::write("Created: {$post['title']} ({$slug})", 'green');
                $inserted++;
            } catch (Throwable $e) {
                CLI::error("Failed {$slug}: " . $e->getMessage());

                return EXIT_ERROR;
            }
        }

        CLI::write("Done. Inserted {$inserted}, skipped {$skipped}.", 'cyan');

        return EXIT_SUCCESS;
    }

    /**
     * @return list<array{title: string, slug: string, summary: string, body: string, published_at?: string}>
     */
    private function samplePosts(): array
    {
        return [
            [
                'title'        => 'Welcome to the Product Store Blog',
                'slug'         => 'welcome-to-the-product-store-blog',
                'summary'      => 'A quick introduction to what you can publish here and how posts appear on the home page.',
                'body'         => '<p>This is your first sample post. Use <strong>Public Content</strong> in the dashboard to write articles, add excerpts, and publish when you are ready.</p><p>Published posts show on the blog index and in the <em>Latest Public Posts</em> section on the home page.</p>',
                'published_at' => '2026-05-10 09:00:00',
            ],
            [
                'title'        => 'Getting Started with Modules',
                'slug'         => 'getting-started-with-modules',
                'summary'      => 'Enable Public, Community, and Personal content from Module Manager.',
                'body'         => '<p>Module Manager lets you turn features on or off without code changes. Start with Public Content for a blog, Community for forums, and Personal for member messages.</p><p>Each module respects roles and permissions configured under User Manager.</p>',
                'published_at' => '2026-05-12 14:30:00',
            ],
            [
                'title'        => 'Tips for Writing Great Excerpts',
                'slug'         => 'tips-for-writing-great-excerpts',
                'summary'      => 'Summaries power home-page teasers and blog listing excerpts.',
                'body'         => '<p>Fill in the <strong>Summary</strong> field for a short teaser. On the blog index, readers see the summary before clicking through.</p><p>You can also use a <code>&lt;!--more--&gt;</code> marker in the body to split intro text from the full article.</p>',
                'published_at' => '2026-05-14 11:15:00',
            ],
            [
                'title'        => 'Security Manager and Clean URLs',
                'slug'         => 'security-manager-and-clean-urls',
                'summary'      => 'Optional CANG identifiers hide numeric IDs in public URLs.',
                'body'         => '<p>When Security Manager is enabled, posts and topics can use opaque <code>c_id</code> values in URLs instead of sequential numbers.</p><p>Configure profiles under Dashboard → Security Manager, then regenerate identifiers if needed.</p>',
                'published_at' => '2026-05-16 16:45:00',
            ],
            [
                'title'        => 'What Is Next for Your Site',
                'slug'         => 'what-is-next-for-your-site',
                'summary'      => 'Ideas for content, community topics, and member engagement.',
                'body'         => '<p>After seeding blog posts, create forum categories, invite members, and tune Web Settings for your site name and description.</p><p>Analytics can show who is online when Web Analytics is enabled in Module Manager.</p>',
                'published_at' => '2026-05-17 08:00:00',
            ],
        ];
    }

    /**
     * @param \CodeIgniter\Database\BaseConnection $db
     */
    private function resolveAuthorId($db): ?int
    {
        if (! $db->tableExists('users')) {
            return null;
        }

        try {
            $admin = $db->table('users')
                ->select('id')
                ->where('is_active', true)
                ->orderBy('id', 'ASC')
                ->limit(1)
                ->get()
                ->getRowArray();

            if (is_array($admin) && (int) ($admin['id'] ?? 0) > 0) {
                return (int) $admin['id'];
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
