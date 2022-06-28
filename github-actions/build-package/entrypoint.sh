#!/bin/sh

#
# ---------------------------------------------------------------------
#
# GLPI tools
#
# @copyright 2017-2022 Teclib' and contributors.
# @licence   https://www.gnu.org/licenses/gpl-3.0.html
# @link      https://github.com/glpi-project/tools
#
# ---------------------------------------------------------------------
#
# LICENSE
#
# This file is part of GLPI tools.
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <https://www.gnu.org/licenses/>.
#
# ---------------------------------------------------------------------
#

composer install --no-progress --no-suggest --no-interaction --prefer-dist

# Check that "plugin-release" script is fetched with project dependencies.
# TODO Embed the release script in "ghcr.io/glpi-project/plugin-builder" docker image.
if [ ! -f "vendor/bin/plugin-release" ]
then
    echo "Project must have 'glpi-project/tools' in its composer dependencies."
    exit 1
fi

# plugin-release requires tags to be fetched.
# TODO Fix this !
git config --global --add safe.directory $(pwd)
git fetch --tags

# --assume-yes  Prevent interactions.
# --dont-check  Do not check if $PLUGIN_VERSION is a valid name for a version.
# --nogithub    Do not create Github release draft.
# --nosign      Do not sign package with gpg.
vendor/bin/plugin-release --assume-yes --dont-check --nogithub --nosign --release $PLUGIN_VERSION --verbose

# Defines output variables.
echo "::set-output name=package-basename::$(find dist/ -type f -name '*.tar.bz2' | xargs -n 1 basename)"
echo "::set-output name=package-path::$(find dist/ -type f -name '*.tar.bz2')"
