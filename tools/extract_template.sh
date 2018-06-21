#!/bin/bash
# /**
#  * ---------------------------------------------------------------------
#  * GLPI - Gestionnaire Libre de Parc Informatique
#  * Copyright (C) 2015-2017 Teclib' and contributors.
#  *
#  * http://glpi-project.org
#  *
#  * based on GLPI - Gestionnaire Libre de Parc Informatique
#  * Copyright (C) 2003-2014 by the INDEPNET Development Team.
#  *
#  * ---------------------------------------------------------------------
#  *
#  * LICENSE
#  *
#  * This file is part of GLPI.
#  *
#  * GLPI is free software; you can redistribute it and/or modify
#  * it under the terms of the GNU General Public License as published by
#  * the Free Software Foundation; either version 2 of the License, or
#  * (at your option) any later version.
#  *
#  * GLPI is distributed in the hope that it will be useful,
#  * but WITHOUT ANY WARRANTY; without even the implied warranty of
#  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  * GNU General Public License for more details.
#  *
#  * You should have received a copy of the GNU General Public License
#  * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
#  * ---------------------------------------------------------------------
# */

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DIR=${DIR%/vendor*}
pushd $DIR > /dev/null

if [ -f "setup.php" ]; then
   #setup.php found: it's a plugin.
   NAME="$(grep -m1 "PLUGIN_.*_VERSION" setup.php|cut -d _ -f 2)"
else
   #using core most probably
   NAME="GLPI"
fi;

POTFILE=${NAME,,}.pot

PHP_SOURCES=`find ./ -name \*.php -not -path "./vendor/*" -not -path "./lib/*"`

if [ ! -d "locales" ]; then
    mkdir locales
fi

# Only strings with domain specified are extracted (use Xt args of keyword param to set number of args needed)
xgettext $PHP_SOURCES -o locales/$POTFILE -L PHP --add-comments=TRANS --from-code=UTF-8 --force-po \
    --keyword=_n:1,2,4t --keyword=__s:1,2t --keyword=__:1,2t --keyword=_e:1,2t --keyword=_x:1c,2,3t --keyword=_ex:1c,2,3t \
    --keyword=_sx:1c,2,3t --keyword=_nx:1c,2,3,5t

#Update main language
LANG=C msginit --no-translator -i locales/$POTFILE -l en_GB -o locales/en_GB.po

popd > /dev/null
