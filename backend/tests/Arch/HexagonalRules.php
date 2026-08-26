<?php

namespace Tests\Arch;

use PHPat\Selector\Classname as SelectorClassname;
use PHPat\Selector\ClassNamespace as SelectorNamespace;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

class HexagonalRules
{
    #[TestRule]
    public function domain_does_not_depend_on_framework_or_other_layers(): Rule
    {
        return PHPat::rule()
            ->classes(new SelectorNamespace('App\Domain', false))
            ->shouldNotDependOn()
            ->classes(
                new SelectorNamespace('Illuminate', false),
                new SelectorNamespace('App\Models', false),
                new SelectorNamespace('App\Http', false),
                new SelectorNamespace('App\Infrastructure', false),
            );
    }

    #[TestRule]
    public function entities_do_not_depend_on_repositories(): Rule
    {
        return PHPat::rule()
            ->classes(new SelectorNamespace('App\Domain\Entities', false))
            ->shouldNotDependOn()
            ->classes(new SelectorNamespace('App\Domain\Repositories', false));
    }

    #[TestRule]
    public function controllers_do_not_use_models_directly(): Rule
    {
        return PHPat::rule()
            ->classes(new SelectorNamespace('App\Http\Controllers', false))
            ->shouldNotDependOn()
            ->classes(new SelectorNamespace('App\Models', false));
    }

    #[TestRule]
    public function eloquent_repositories_implement_domain_repositories(): Rule
    {
        return PHPat::rule()
            ->classes(new SelectorNamespace('App\Infrastructure\Repositories\Eloquent', false))
            ->shouldImplement()
            ->classes(new SelectorNamespace('App\Domain\Repositories', false));
    }

    #[TestRule]
    public function db_facade_only_inside_infrastructure(): Rule
    {
        return PHPat::rule()
            ->classes(new SelectorNamespace('App', false))
            ->excluding(new SelectorNamespace('App\Infrastructure', false))
            ->shouldNotDependOn()
            ->classes(new SelectorClassname('Illuminate\Support\Facades\DB', false));
    }
}