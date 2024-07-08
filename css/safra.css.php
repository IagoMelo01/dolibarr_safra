<?php
/* Copyright (C) 2024 SuperAdmin
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
 */

/**
 * \file    safra/css/safra.css.php
 * \ingroup safra
 * \brief   CSS file for module Safra.
 */

//if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER','1');	// Not disabled because need to load personalized language
//if (!defined('NOREQUIREDB'))   define('NOREQUIREDB','1');	// Not disabled. Language code is found on url.
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
//if (!defined('NOREQUIRETRAN')) define('NOREQUIRETRAN','1');	// Not disabled because need to do translations
//if (!defined('NOCSRFCHECK'))   define('NOCSRFCHECK', 1);		// Should be disable only for special situation
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1); // File must be accessed by logon page so without login
}
//if (! defined('NOREQUIREMENU'))   define('NOREQUIREMENU',1);  // We need top menu content
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

session_cache_limiter('public');
// false or '' = keep cache instruction added by server
// 'public'  = remove cache instruction added by server
// and if no cache-control added later, a default cache delay (10800) will be added by PHP.

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/../main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/../main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

// Load user to have $user->conf loaded (not done by default here because of NOLOGIN constant defined) and load permission if we need to use them in CSS
/*if (empty($user->id) && !empty($_SESSION['dol_login'])) {
	$user->fetch('',$_SESSION['dol_login']);
	$user->getrights();
}*/


// Define css type
header('Content-type: text/css');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) {
	header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
	header('Cache-Control: no-cache');
}

?>
<style>
div.mainmenu.safra::before {
	content: "\f249";
}
div.mainmenu.safra {
	background-image: none;
}

.myclasscss {
	/* ... */
}

.field_geo_json{
	display: none;
}
.field_wkt{
	display: none;
}
.field_bbox{
	display: none;
}

#map {
	width: 100%;
	height: 100%;
}



	.container {
		justify-content: start;
		align-items: auto;
		align-content: start;
	}
	.item {
		margin: 10px;
		/* border: 5px solid red; */
		width: 46rem;
	}

	#mapIndex {
		width: 70%;
		height: 350px;
	}
	#mapList {
		z-index: 0;
		width: 95%;
		height: 300px;
	}
	#mapCRUD {
		z-index: 0;
		width: 100%;
		height: 30rem;
	}
	#mapShow {
		z-index: 0; 
		width: 95%;
		height: 300px;
	}
	#mapSetup {
		z-index: 0;
		width: 95%;
		height: 400px;
	}
	#mapcadastre {
		z-index: 0;
		width: 100%;
		height: 650px;
	}

@media only screen and (min-width: 1024px) {
	.container {
		display: flex;
		flex-wrap: nowrap;
		flex-direction: row;
		justify-content: start;
		align-items: auto;
		align-content: start;
	}
	.item {
		flex: 0 0 auto;
		margin: 10px 0 0 10px;
	}
	#mapList {
		z-index: 0;
		width: 29rem;
		height: 40rem;
	}
	#mapCRUD {
		z-index: 0;
		width: 29rem;
		height: 40rem;
	}
	#mapShow {
		z-index: 0;
		width: 95%;
		height: 380px;
	}
	#mapSetup {
		z-index: 0;
		width: 55%;
		height: 400px;
	}
	#mapcadastre {
		z-index: 0;
		width: 95%;
		height: 650px;
	}
	#mapIndex {
		width: 100%;
		height: 30rem;
	}
}



	/* creating css loader */

#boxLoading {
    width: 6rem;
    height: 6rem;
    border: 5px solid #ffffff;
    border-top: 6px solid #49729e;
    border-radius: 100%;
    margin: auto;
    animation: spin 1s infinite linear;
}
#boxLoading.display {
    visibility: visible;
}
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

</style> 