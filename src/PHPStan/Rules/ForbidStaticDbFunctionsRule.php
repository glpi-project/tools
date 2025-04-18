<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI tools
 *
 * @copyright 2017-2023 Teclib' and contributors.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/glpi-project/tools
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI tools.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace GlpiProject\Tools\PHPStan\Rules;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Global_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\FileTypeMapper;

class ForbidStaticDbFunctionsRule implements Rule
{
    private const FORBIDDEN_STATIC_METHODS = [
        'getTable',
        'getForeignKeyField',
    ];

    public function getNodeType(): string
    {
        return Node\Expr\StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach (self::FORBIDDEN_STATIC_METHODS as $method) {
            if ($node instanceof Node\Expr\StaticCall && $node->name->toString() === $method) {
                $errors[] = RuleErrorBuilder::message(sprintf('L\'utilisation de la méthode %s() est interdite.', $method))->build();
            }
        }
        return $errors;
    }
}
