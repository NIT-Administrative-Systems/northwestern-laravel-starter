<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Code Highlighting
    |--------------------------------------------------------------------------
    |
    | Highlights code blocks with Shiki, in the theme named here. Install Shiki first.
    |
    */

    'code_highlighting' => [
        'enabled' => false,
        'theme' => 'github-light',
    ],

    /*
    |--------------------------------------------------------------------------
    | Heading Anchors
    |--------------------------------------------------------------------------
    |
    | Adds an anchor to every heading. `render_anchors_as_links` makes them links.
    |
    */

    'add_anchors_to_headings' => true,

    'render_anchors_as_links' => false,

    /*
    |--------------------------------------------------------------------------
    | CommonMark Options
    |--------------------------------------------------------------------------
    |
    | Options passed to league/commonmark, which renders the Markdown.
    |
    */

    'commonmark_options' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Rendering Markdown is costly, so the HTML is cached in this store.
    | Null uses the default cache store, and false turns caching off.
    |
    */

    'cache_store' => null,

    /*
    |--------------------------------------------------------------------------
    | Renderer
    |--------------------------------------------------------------------------
    |
    | The class that turns Markdown into HTML; replace it to change the rendering.
    |
    */

    'renderer_class' => Spatie\LaravelMarkdown\MarkdownRenderer::class,

    /*
    |--------------------------------------------------------------------------
    | Extensions, Renderers and Parsers
    |--------------------------------------------------------------------------
    |
    | Extensions, block and inline renderers, and inline parsers added to the
    | CommonMark environment. FrontMatterExtension keeps a document's YAML
    | front matter out of the HTML that the Markdown is rendered into.
    |
    */

    'extensions' => [
        League\CommonMark\Extension\FrontMatter\FrontMatterExtension::class,
    ],

    'block_renderers' => [],

    'inline_renderers' => [],

    'inline_parsers' => [],

];
