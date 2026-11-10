<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\View\View;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        $this->view = new View(dirname(__DIR__, 2) . '/Fixtures/views');
    }

    public function testOutputIsEscapedAndWrappedInLayout(): void
    {
        $this->view->share('shared', 'ortak');

        $html = $this->view->render('pages/hello', ['name' => '<b>Ali</b>'], 'layouts/test');

        self::assertSame("<layout><p>&lt;b&gt;Ali&lt;/b&gt;|ortak</p>\n</layout>\n", $html);
    }

    public function testTemplateScopeIsIsolatedFromViewObject(): void
    {
        self::assertSame('isolated', trim($this->view->partial('pages/scope')));
    }

    public function testPathTraversalIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->view->partial('../../composer');
    }
}
