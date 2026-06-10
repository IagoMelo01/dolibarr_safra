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

.safra-dashboard__intro {
        max-width: 900px;
        margin: 0 0 18px;
        color: #667085;
        font-size: .95rem;
        line-height: 1.55;
}

.safra-dashboard__grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 18px;
}

.safra-card {
        grid-column: span 4;
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        padding: 18px 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-height: 0;
}

.safra-card--wide {
        grid-column: 1 / -1;
}

.safra-card--main {
        grid-column: span 8;
}

.safra-card--side {
        grid-column: span 4;
}

.safra-card--half {
        grid-column: span 6;
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

.safra-card__header a {
        color: #176b52;
        font-size: .82rem;
        font-weight: 700;
        text-decoration: none;
}

.safra-card__header p {
        margin: 5px 0 0;
        color: #667085;
        font-size: .82rem;
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

.safra-operation-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
}

.safra-operation-metric {
        min-height: 108px;
        padding: 14px;
        border: 1px solid #dce5e0;
        border-left: 4px solid #667085;
        border-radius: 8px;
        background: #fbfdfc;
}

.safra-operation-metric--running {
        border-left-color: #d58b16;
        background: #fffaf0;
}

.safra-operation-metric--overdue {
        border-left-color: #c0362c;
        background: #fff5f4;
}

.safra-operation-metric--done {
        border-left-color: #21845f;
        background: #f2faf6;
}

.safra-operation-metric__value {
        color: #173f31;
        font-size: 1.8rem;
        font-weight: 700;
        line-height: 1.1;
}

.safra-operation-metric__label {
        margin-top: 7px;
        color: #344054;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
}

.safra-operation-metric__description {
        margin-top: 4px;
        color: #667085;
        font-size: .76rem;
        line-height: 1.4;
}

.safra-operation-area {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 28px;
        padding-top: 12px;
        border-top: 1px solid #e4e9e6;
        color: #667085;
        font-size: .78rem;
}

.safra-operation-area span,
.safra-operation-area strong {
        display: block;
}

.safra-operation-area strong {
        margin-bottom: 2px;
        color: #176b52;
        font-size: 1rem;
}

.safra-action-grid {
        display: grid;
        gap: 8px;
}

.safra-action-card {
        display: grid;
        grid-template-columns: 32px 1fr;
        gap: 10px;
        align-items: start;
        padding: 10px;
        border: 1px solid #dce5e0;
        border-radius: 7px;
        background: #fbfdfc;
        color: #173f31;
        text-decoration: none;
}

.safra-action-card:hover {
        border-color: #8fb9a5;
        background: #f2faf6;
}

.safra-action-card > .fas {
        display: grid;
        place-items: center;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        background: #e7f3ed;
        color: #176b52;
}

.safra-action-card strong,
.safra-action-card small {
        display: block;
}

.safra-action-card small {
        margin-top: 2px;
        color: #667085;
        font-size: .73rem;
        line-height: 1.35;
}

.safra-activity-list {
        display: grid;
        gap: 0;
        margin: 0;
        padding: 0;
        list-style: none;
}

.safra-activity-list__item {
        padding: 12px 0;
        border-bottom: 1px solid #e4e9e6;
}

.safra-activity-list__item:first-child {
        padding-top: 2px;
}

.safra-activity-list__item:last-child {
        padding-bottom: 0;
        border-bottom: 0;
}

.safra-activity-list__top {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        justify-content: space-between;
}

.safra-activity-list__ref {
        margin-bottom: 2px;
        font-size: .78rem;
}

.safra-activity-list__top strong {
        color: #1d2939;
        font-size: .92rem;
}

.safra-activity-list__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 12px;
        margin-top: 7px;
        color: #667085;
        font-size: .76rem;
}

.safra-activity-status {
        flex: 0 0 auto;
        padding: 3px 7px;
        border-radius: 999px;
        background: #eef2f5;
        color: #475467;
        font-size: .7rem;
        font-weight: 700;
}

.safra-activity-status--status-2 {
        background: #fff5df;
        color: #8a5700;
}

.safra-activity-status--overdue {
        background: #fff0ee;
        color: #a3241b;
}

.safra-context-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
}

.safra-context-card {
        padding: 14px;
        border: 1px solid #e4e9e6;
        border-radius: 8px;
        background: #fbfdfc;
}

.safra-context-card__value {
        color: #176b52;
        font-size: 1.35rem;
        font-weight: 700;
}

.safra-context-card__label {
        margin-top: 5px;
        color: #344054;
        font-size: .76rem;
        font-weight: 700;
        text-transform: uppercase;
}

.safra-context-card__description {
        margin-top: 4px;
        color: #667085;
        font-size: .75rem;
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

.safra-chart-container--large {
        height: 360px;
}

.safra-chart-container > .safra-empty {
        position: absolute;
        inset: 0;
        display: grid;
        place-items: center;
        padding: 24px;
        text-align: center;
}

.safra-chart {
        position: absolute;
        inset: 0;
        width: 100% !important;
        height: 100% !important;
}

.safra-chart__meta {
        margin-top: 12px;
        font-size: 0.85rem;
        color: #64748b;
}

.safra-dashboard-chart-header {
        align-items: flex-end;
}

.safra-dashboard-field-switcher {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        align-items: center;
        justify-content: flex-end;
}

.safra-dashboard-field-switcher label {
        color: #475467;
        font-size: .76rem;
        font-weight: 700;
}

.safra-dashboard-field-switcher select {
        min-width: 190px;
}

.safra-dashboard-field-switcher .button {
        margin: 0;
}

.safra-dashboard-chart-field {
        color: #667085;
        font-size: .8rem;
}

.safra-dashboard-chart-field strong {
        color: #176b52;
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

@media (max-width: 1050px) {
        .safra-card,
        .safra-card--side,
        .safra-card--half {
                grid-column: span 6;
        }

        .safra-card--wide,
        .safra-card--main {
                grid-column: 1 / -1;
        }

        .safra-operation-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
        }
}

@media (max-width: 768px) {
        .safra-card,
        .safra-card--wide,
        .safra-card--main,
        .safra-card--side,
        .safra-card--half {
                grid-column: 1 / -1;
        }

        .safra-card {
                padding: 16px;
        }

        .safra-operation-grid,
        .safra-context-grid {
                grid-template-columns: 1fr;
        }

        .safra-dashboard-chart-header {
                align-items: stretch;
                flex-direction: column;
        }

        .safra-dashboard-field-switcher {
                align-items: stretch;
                justify-content: flex-start;
        }

        .safra-dashboard-field-switcher label {
                width: 100%;
        }

        .safra-dashboard-field-switcher select {
                flex: 1 1 190px;
                min-width: 0;
        }

        .safra-weather__current {
                flex-direction: column;
        }

        .safra-weather__metrics {
                flex-direction: row;
                flex-wrap: wrap;
        }
}

#id-container{
        overflow: auto;
}

.listform{
        overflow: scroll;
}

.safra-filter-panel {
        margin: 14px 0 18px;
        padding: 12px;
        border: 1px solid #d8dee4;
        background: #f8fafb;
}

.safra-filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px;
}

.safra-filter-grid label {
        display: grid;
        gap: 4px;
        color: #344054;
        font-size: 12px;
        font-weight: 600;
}

.safra-filter-grid input,
.safra-filter-grid select,
.safra-filter-grid .select2-container {
        box-sizing: border-box;
        width: 100% !important;
}

.safra-filter-actions {
        margin-top: 10px;
        text-align: right;
}

.safra-kanban {
        display: grid;
        grid-template-columns: repeat(3, minmax(260px, 1fr));
        gap: 14px;
        align-items: start;
}

.safra-kanban-column {
        min-height: 240px;
        border: 1px solid #d8dee4;
        background: #f6f8fa;
}

.safra-kanban-column > header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        border-bottom: 1px solid #d8dee4;
        background: #eef2f5;
}

.safra-kanban-column > header span {
        min-width: 24px;
        padding: 2px 6px;
        border-radius: 999px;
        background: #ffffff;
        text-align: center;
}

.safra-kanban-column--overdue > header {
        background: #fff1f0;
        color: #a3241b;
}

.safra-kanban-cards {
        display: grid;
        gap: 10px;
        padding: 10px;
}

.safra-kanban-card {
        padding: 10px;
        border: 1px solid #d8dee4;
        background: #ffffff;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .06);
}

.safra-kanban-card__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
}

.safra-kanban-card h3 {
        margin: 8px 0;
        font-size: 14px;
}

.safra-kanban-card__meta {
        display: grid;
        gap: 4px;
        color: #667085;
        font-size: 12px;
}

.safra-kanban-deadline {
        color: #a3241b;
        font-weight: 600;
}

.safra-kanban-empty {
        padding: 18px 8px;
        color: #667085;
        text-align: center;
}

.safra-priority {
        padding: 2px 6px;
        border-radius: 3px;
        background: #eef2f5;
        color: #475467;
        font-size: 11px;
}

.safra-priority--2,
.safra-priority--3 {
        background: #fff1f0;
        color: #a3241b;
}

.safra-report-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(160px, 1fr));
        gap: 12px;
        margin: 12px 0 18px;
}

.safra-report-summary div {
        padding: 12px;
        border: 1px solid #d8dee4;
        background: #ffffff;
}

.safra-report-summary strong,
.safra-report-summary span {
        display: block;
}

.safra-report-summary strong {
        color: #176b52;
        font-size: 22px;
}

.safra-report-summary span {
        margin-top: 3px;
        color: #667085;
        font-size: 12px;
}

@media (max-width: 980px) {
        .safra-kanban {
                grid-template-columns: 1fr;
        }
}

@media (max-width: 620px) {
        .safra-report-summary {
                grid-template-columns: 1fr;
        }
}

.button.safra-btn-primary {
        background: #176b52 !important;
        border-color: #105440 !important;
        color: #fff !important;
}

.button.safra-btn-secondary {
        background: #eef4f1 !important;
        border-color: #c5d7cf !important;
        color: #173f31 !important;
}

.safra-document-intro {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin: 12px 0 16px;
}

.safra-document-intro > div {
        display: grid;
        grid-template-columns: 30px 1fr;
        gap: 2px 8px;
        align-items: start;
        padding: 14px;
        border: 1px solid #d7e2dc;
        border-radius: 8px;
        background: #f8fbf9;
}

.safra-document-intro > div > .fas {
        grid-row: span 2;
        color: #176b52;
        font-size: 20px;
}

.safra-document-intro > div > span:last-child {
        color: #667085;
        font-size: 12px;
}

.safra-camera-upload {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) minmax(280px, 2fr);
        gap: 16px;
        align-items: center;
        margin: 16px 0;
        padding: 16px;
        border: 1px solid #b7d4c6;
        border-radius: 8px;
        background: #eef8f3;
}

.safra-camera-upload .formattachnewfile,
.safra-camera-upload .attacharea {
        margin: 0;
        padding: 0;
}

.safra-manual {
        max-width: 1180px;
        margin: 0 auto;
        color: #1d2939;
}

.safra-manual-hero {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr);
        gap: 24px;
        align-items: center;
        padding: 34px;
        border-radius: 14px;
        background: linear-gradient(135deg, #174f3c 0%, #176b52 58%, #2b8a68 100%);
        color: #fff;
}

.safra-manual-hero h1 {
        margin: 8px 0;
        color: #fff;
        font-size: clamp(28px, 5vw, 44px);
        line-height: 1.08;
}

.safra-manual-hero p {
        max-width: 720px;
        margin: 0;
        color: #e1f2ea;
        font-size: 16px;
        line-height: 1.6;
}

.safra-manual-kicker {
        color: #bde7d3;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
}

.safra-manual-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 22px;
}

.safra-manual-hero .button.safra-btn-primary {
        background: #fff !important;
        border-color: #fff !important;
        color: #174f3c !important;
}

.safra-manual-hero .button.safra-btn-secondary {
        background: rgba(255, 255, 255, .12) !important;
        border-color: rgba(255, 255, 255, .42) !important;
        color: #fff !important;
}

.safra-manual-rule {
        display: grid;
        gap: 8px;
        padding: 20px;
        border: 1px solid rgba(255, 255, 255, .26);
        border-radius: 10px;
        background: rgba(255, 255, 255, .1);
}

.safra-manual-rule strong {
        font-size: 18px;
}

.safra-manual-rule span {
        color: #e1f2ea;
        line-height: 1.5;
}

.safra-manual-shortcuts {
        position: sticky;
        z-index: 4;
        top: 0;
        display: flex;
        gap: 6px;
        overflow-x: auto;
        margin: 14px 0;
        padding: 10px;
        border: 1px solid #d7e2dc;
        border-radius: 10px;
        background: rgba(255, 255, 255, .96);
}

.safra-manual-shortcuts a {
        flex: 0 0 auto;
        padding: 7px 10px;
        border-radius: 6px;
        color: #175a44;
        font-weight: 600;
        text-decoration: none;
}

.safra-manual-shortcuts a:hover {
        background: #edf7f2;
}

.safra-manual-section {
        scroll-margin-top: 80px;
        margin: 18px 0;
        padding: 24px;
        border: 1px solid #dce5e0;
        border-radius: 12px;
        background: #fff;
}

.safra-manual-heading {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 18px;
}

.safra-manual-heading > .fas {
        display: grid;
        place-items: center;
        width: 42px;
        height: 42px;
        border-radius: 9px;
        background: #e8f4ee;
        color: #176b52;
        font-size: 20px;
}

.safra-manual-heading h2,
.safra-manual-heading p {
        margin: 0;
}

.safra-manual-heading h2 {
        color: #173f31;
        font-size: 22px;
}

.safra-manual-heading p {
        margin-top: 4px;
        color: #667085;
}

.safra-flow {
        display: grid;
        grid-template-columns: repeat(9, auto);
        gap: 8px;
        align-items: center;
}

.safra-flow a {
        display: grid;
        gap: 5px;
        min-height: 122px;
        padding: 14px;
        border: 1px solid #cfe0d7;
        border-radius: 8px;
        background: #f8fbf9;
        color: #173f31;
        text-decoration: none;
}

.safra-flow a span {
        display: grid;
        place-items: center;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        background: #176b52;
        color: #fff;
        font-weight: 700;
}

.safra-flow a small {
        color: #667085;
        line-height: 1.4;
}

.safra-flow > .fas,
.safra-status-flow > .fas {
        color: #76a58f;
}

.safra-status-flow {
        display: flex;
        gap: 12px;
        align-items: center;
        justify-content: center;
        margin-top: 18px;
        padding: 14px;
        border-radius: 8px;
        background: #f5f8f6;
}

.safra-status-flow div {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 2px 7px;
        align-items: center;
}

.safra-status-flow small {
        grid-column: 2;
        color: #667085;
}

.safra-status-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
}

.safra-status-dot--planned {
        background: #667085;
}

.safra-status-dot--running {
        background: #e09f24;
}

.safra-status-dot--done {
        background: #21845f;
}

.safra-step-list {
        display: grid;
        gap: 12px;
        margin: 0;
        padding: 0;
        list-style: none;
}

.safra-step-list li {
        display: grid;
        grid-template-columns: 34px 1fr;
        gap: 12px;
        align-items: start;
}

.safra-step-list li > span {
        display: grid;
        place-items: center;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #176b52;
        color: #fff;
        font-weight: 700;
}

.safra-step-list p,
.safra-manual-grid p,
.safra-manual-callout p,
.safra-manual-warning p {
        margin: 4px 0 0;
        color: #667085;
        line-height: 1.5;
}

.safra-manual-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 12px;
}

.safra-manual-grid article {
        padding: 16px;
        border: 1px solid #dce5e0;
        border-radius: 8px;
        background: #fbfdfc;
}

.safra-manual-grid article > .fas {
        color: #176b52;
        font-size: 22px;
}

.safra-manual-grid h3 {
        margin: 10px 0 0;
        color: #173f31;
}

.safra-manual-grid article a {
        display: inline-block;
        margin-top: 10px;
        color: #176b52;
        font-weight: 700;
}

.safra-manual-callout,
.safra-manual-warning {
        display: grid;
        grid-template-columns: 30px 1fr;
        gap: 10px;
        margin-top: 14px;
        padding: 14px;
        border-radius: 8px;
}

.safra-manual-callout {
        border: 1px solid #cfe0d7;
        background: #eef8f3;
}

.safra-manual-warning {
        border: 1px solid #ead6a5;
        background: #fff8e7;
}

.safra-manual-callout > .fas {
        color: #176b52;
        font-size: 20px;
}

.safra-manual-warning > .fas {
        color: #a56b00;
        font-size: 20px;
}

.safra-faq {
        display: grid;
        gap: 8px;
}

.safra-faq details {
        padding: 12px 14px;
        border: 1px solid #dce5e0;
        border-radius: 7px;
}

.safra-faq summary {
        cursor: pointer;
        color: #173f31;
        font-weight: 700;
}

.safra-faq p {
        margin: 8px 0 0;
        color: #667085;
}

.safra-manual-checklist {
        display: grid;
        gap: 8px;
        margin: 18px 0 30px;
        padding: 24px;
        border-radius: 12px;
        background: #173f31;
        color: #fff;
}

.safra-manual-checklist h2 {
        margin: 0 0 6px;
        color: #fff;
}

.safra-manual-checklist label {
        display: flex;
        gap: 8px;
        align-items: center;
}

@media (max-width: 980px) {
        .safra-document-intro,
        .safra-manual-hero {
                grid-template-columns: 1fr;
        }

        .safra-flow {
                grid-template-columns: 1fr;
        }

        .safra-flow > .fas {
                transform: rotate(90deg);
                justify-self: center;
        }
}

@media (max-width: 700px) {
        .safra-camera-upload {
                grid-template-columns: 1fr;
        }

        .safra-status-flow {
                align-items: stretch;
                flex-direction: column;
        }

        .safra-status-flow > .fas {
                align-self: center;
                transform: rotate(90deg);
        }

        .safra-manual-hero,
        .safra-manual-section,
        .safra-manual-checklist {
                padding: 18px;
        }
}


</style> 
