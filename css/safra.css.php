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
               width: 46rem;
       }

       .map-card {
               background: #fff;
               border-radius: 8px;
               box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
               overflow: hidden;
       }

       #mapIndex {
               position: relative;
               width: 100%;
               height: 350px;
       }

	#mapList {
		z-index: 0;
		width: 100%;
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
		flex-direction: column;
		justify-content: start;
		align-items: auto;
		align-content: start;
	}
	.item {
		flex: 0 0 auto;
                width: 100%;
		margin: 10px 0 0 10px;
		/* max-width: 45%; */
	}
	#mapList {
		z-index: 0;
		width: 100%;
		height: 15rem;
	}
	#mapCRUD {
		z-index: 0;
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
                position: relative;
                width: 100%;
                height: 30rem;
        }
}



	/* creating css loader */

#boxLoading {
    display: none;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 6rem;
    height: 6rem;
    border: 5px solid #ffffff;
    border-top: 6px solid #49729e;
    border-radius: 100%;
    margin: auto;
    animation: spin 1s infinite linear;
    background-color: rgba(255, 255, 255, 0.8);
}
#boxLoading.display {
    display: block;
}

.area-tooltip {
    background: rgba(255, 255, 255, 0.9);
    color: #333;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 2px 4px;
    font-size: 0.8rem;

}
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}


.safra-dashboard {
        margin: 0 auto;
        max-width: 1280px;
        padding: 0 16px 32px;
}

.safra-dashboard__grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 18px;
}

.safra-card {
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        padding: 18px 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-height: 0;
}

.safra-card__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 4px;
}

.safra-card__header h2 {
        font-size: 1.05rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
}

.safra-chip {
        display: inline-flex;
        align-items: center;
        background: #eef4ff;
        color: #1d4ed8;
        border-radius: 999px;
        padding: 2px 10px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.04em;
}

.safra-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 14px;
}

.safra-summary-card {
        background: linear-gradient(135deg, #1d4ed8, #0ea5e9);
        color: #ffffff;
        padding: 16px;
        border-radius: 10px;
        min-height: 130px;
        display: flex;
        flex-direction: column;
}

.safra-summary-card:nth-child(2n) {
        background: linear-gradient(135deg, #16a34a, #22d3ee);
}

.safra-summary-card:nth-child(3n) {
        background: linear-gradient(135deg, #f97316, #facc15);
}

.safra-summary-card__value {
        font-size: 1.8rem;
        font-weight: 700;
        line-height: 1.2;
}

.safra-summary-card__label {
        text-transform: uppercase;
        font-size: 0.78rem;
        letter-spacing: 0.06em;
        opacity: 0.85;
        margin-top: 6px;
}

.safra-summary-card__description {
        font-size: 0.8rem;
        margin-top: auto;
        opacity: 0.85;
}

.safra-map {
        position: relative;
        width: 100%;
        min-height: 320px;
        height: clamp(320px, 35vh, 480px);
        border-radius: 10px;
        overflow: hidden;
}

.safra-card--chart {
        min-height: 0;
}

.safra-chart-container {
        position: relative;
        width: 100%;
        height: 260px;
}

.safra-chart {
        position: absolute;
        inset: 0;
        width: 100% !important;
        height: 100% !important;
}

.safra-empty {
        margin: 6px 0 0;
        color: #64748b;
        font-style: italic;
}

.safra-insights {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 12px;
}

.safra-insights__item {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        padding: 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 6px;
}

.safra-insights__value {
        font-size: 1.35rem;
        font-weight: 700;
        color: #0f766e;
}

.safra-insights__label {
        font-size: 0.82rem;
        color: #1f2937;
        letter-spacing: 0.06em;
        text-transform: uppercase;
}

.safra-insights__description {
        font-size: 0.78rem;
        color: #475569;
}

.safra-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
}

.safra-list__item {
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 10px;
}

.safra-list__item:last-child {
        border-bottom: none;
        padding-bottom: 0;
}

.safra-list__primary {
        font-weight: 600;
        color: #1e293b;
}

.safra-list__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 0.8rem;
        color: #475569;
}

.safra-weather {
        display: flex;
        flex-direction: column;
        gap: 14px;
}

.safra-weather__current {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
}

.safra-weather__temp {
        font-size: 2.6rem;
        font-weight: 700;
        color: #f97316;
        line-height: 1;
}

.safra-weather__description {
        font-size: 0.95rem;
        margin-top: 4px;
        color: #334155;
        font-weight: 600;
}

.safra-weather__location {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.08em;
        margin-top: 6px;
}

.safra-weather__metrics {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
        font-size: 0.82rem;
        color: #475569;
}

.safra-weather__forecast-title {
        font-weight: 600;
        color: #1e293b;
}

.safra-weather__forecast {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
}

.safra-weather__forecast-day {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        font-size: 0.8rem;
        color: #475569;
}

.safra-weather__forecast-temp {
        font-weight: 600;
        color: #0f766e;
}

.safra-weather__forecast-precip {
        font-size: 0.75rem;
        color: #1e293b;
}

@media (max-width: 768px) {
        .safra-card {
                padding: 16px;
        }

        .safra-weather__current {
                flex-direction: column;
        }

        .safra-weather__metrics {
                flex-direction: row;
                flex-wrap: wrap;
        }
}

</style> 
