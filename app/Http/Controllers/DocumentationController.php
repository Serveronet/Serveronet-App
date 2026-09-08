<?php

namespace App\Http\Controllers;

use App\Dicts\DocsMapping;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use Illuminate\Contracts\View\Factory as ViewFactory;

class DocumentationController extends Controller
{
    public function getDoc($doc_tag = DocsMapping::documentation_index)
    {
        $mapping = DocsMapping::mapping;

        if (! isset($mapping[$doc_tag])) {
            abort(404);
        }

        $config = [
            'default_attributes' => [
                Link::class => [
                    'class' => 'text-decoration-none text-dark',
                ],
            ],
            'html_input' => 'allow',
            'table_of_contents' => [
                'html_class' => 'table-of-contents text-decoration-none text-success',
                'position' => 'top',
                'style' => 'bullet',
                'min_heading_level' => 1,
                'max_heading_level' => 6,
                'normalize' => 'relative',
                'placeholder' => null,
            ],
            'heading_permalink' => [
                'html_class' => 'heading-permalink',
                'id_prefix' => 'content',
                'apply_id_to_heading' => false,
                'heading_class' => 'text-decoration-none text-dark',
                'fragment_prefix' => 'content',
                'insert' => 'before',
                'min_heading_level' => 1,
                'max_heading_level' => 6,
                'title' => 'Permalink',
                'symbol' => '#',
                'aria_hidden' => true,
            ],
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());

        $environment->addExtension(new DefaultAttributesExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new HeadingPermalinkExtension());

        $converter = new MarkdownConverter($environment);

        $factory = app(ViewFactory::class);
        $factory->addExtension('md', 'blade');
        $markdown = '';

        if ($doc_tag == 'documentation_index') {

            foreach ($mapping as $key => $value) {

                $input = '';
                $toc = [];

                if ($key === 'documentation_index')
                continue;

                $docContent = Storage::disk('documentation')->get($key . '.md');

                $toc = $this->extractHighLevelTableOfContents($docContent);

                $input .= '##### **[' . $value['label'] . '](' . $key . ')' . '** #####';
                $input .= " \n ";
                $input .= '*' . $value['description'] . '*';
                $input .= " <br> ";

                $tocContent = '';
                $tocContent .= $factory->make('documentation_toc', ['toc' => $toc, 'doc_tag' => $key], [])->render();

                $markdown .= $converter->convert($input);
                $markdown .= $tocContent;
                $markdown .= " <br> ";
                $markdown .= " <br> ";
            }
            $documentation_title = $mapping['documentation_index']['label'];

            return view('documentation', compact('markdown', 'documentation_title', 'mapping', 'doc_tag'));
        }

        $environment->addExtension(new TableOfContentsExtension());

        $docContent = Storage::disk('documentation')->get($doc_tag . '.md');
        $documentation_title = $mapping[$doc_tag]['label'];

        $markdown = $converter->convert($docContent);

        return view('documentation', compact('markdown', 'documentation_title', 'mapping', 'doc_tag'));
    }

    protected function extractHighLevelTableOfContents(string $document): array
    {
        $toc = collect(explode("\n", $document))
            ->filter(function (string $line) {
                return Str::startsWith($line, ['# ', '## ', '### ']);
            })
            ->map(function (string $line) {
                $hashesCount = strlen(explode(' ', $line)[0]) + 1;

                $level = $hashesCount <= 2 ? 2 : 3;

                return [
                    'level' => $level,
                    'title' => $title = trim(Str::after($line, '# ')),
                    'anchor' => Str::slug($title),
                ];
            });

        $toc = $toc->toArray();
        return $toc;
    }
}
