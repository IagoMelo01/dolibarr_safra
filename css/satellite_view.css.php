<?php
/* Copyright (C) 2024 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    safra/css/satellite_view.css.php
 * \ingroup safra
 * \brief   Dedicated styles for the satellite analysis dashboards.
 */

if (!defined('NOREQUIRESOC')) {
    define('NOREQUIRESOC', '1');
}
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
    define('NOLOGIN', 1);
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

session_cache_limiter('public');

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
}
if (!$res && !empty($_SERVER['SCRIPT_FILENAME'])) {
    $tmp = $_SERVER['SCRIPT_FILENAME'];
    $tmp2 = realpath(__FILE__);
    $i = strlen($tmp) - 1;
    $j = strlen($tmp2) - 1;
    while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
        $i--;
        $j--;
    }
    if ($i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . '/main.inc.php')) {
        $res = @include substr($tmp, 0, ($i + 1)) . '/main.inc.php';
    }
    if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php')) {
        $res = @include dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php';
    }
}
if (!$res && file_exists('../main.inc.php')) {
    $res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

header('Content-type: text/css');
if (empty($dolibarr_nocache)) {
    header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
    header('Cache-Control: no-cache');
}
?>
.satellite-page {
    display: flex;
    flex-direction: column;
    gap: 24px;
    margin-top: 12px;
}

.satellite-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: stretch;
}

.satellite-form {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    align-items: flex-end;
    background: #ffffff;
    padding: 20px;
    border-radius: 14px;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
    border: 1px solid rgba(148, 163, 184, 0.2);
    flex: 1 1 520px;
}

.satellite-form__field {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 190px;
    flex: 1 1 190px;
}

.satellite-form__field label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #1f2937;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.satellite-select,
.satellite-form__field select {
    border: 1px solid #cbd5f5;
    border-radius: 10px;
    padding: 10px 14px;
    background: #ffffff;
    font-size: 0.95rem;
    color: #0f172a;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.satellite-select:focus,
.satellite-form__field select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    outline: none;
}

.satellite-form__actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 0 0 auto;
}

.satellite-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 12px;
    border: none;
    background-image: linear-gradient(135deg, #2563eb, #0ea5e9);
    color: #ffffff;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    box-shadow: 0 12px 24px rgba(37, 99, 235, 0.25);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    text-decoration: none;
}

.satellite-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 30px rgba(37, 99, 235, 0.3);
}

.satellite-button:focus {
    outline: 2px solid #1d4ed8;
    outline-offset: 2px;
}

.satellite-button--ghost {
    background: rgba(37, 99, 235, 0.1);
    color: #1d4ed8;
    border: 1px solid rgba(37, 99, 235, 0.35);
    box-shadow: none;
    flex: 0 0 auto;
    align-self: center;
}

.satellite-button--ghost:hover {
    box-shadow: 0 12px 24px rgba(37, 99, 235, 0.16);
}

.satellite-button.is-disabled,
.satellite-button[aria-disabled="true"] {
    cursor: not-allowed;
    opacity: 0.55;
    transform: none;
    box-shadow: none;
}

.satellite-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 16px;
}

.satellite-metric {
    background: #ffffff;
    border-radius: 16px;
    padding: 18px 20px;
    box-shadow: 0 18px 38px rgba(15, 23, 42, 0.1);
    border: 1px solid rgba(148, 163, 184, 0.22);
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.satellite-metric__label {
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

.satellite-metric__value {
    font-size: 1.2rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.35;
}

.satellite-metric__value.is-loading {
    color: #2563eb;
}

.satellite-metric__value.is-positive {
    color: #0f9d58;
}

.satellite-metric__value.is-warning {
    color: #d97706;
}

.satellite-metric__value.is-error {
    color: #d93025;
}

.satellite-layout {
    display: grid;
    grid-template-columns: minmax(0, 2.15fr) minmax(280px, 1fr);
    gap: 24px;
    align-items: flex-start;
}

.satellite-layout__main,
.satellite-layout__aside {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.satellite-card {
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 24px 46px rgba(15, 23, 42, 0.12);
    border: 1px solid rgba(148, 163, 184, 0.22);
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.satellite-card--map {
    padding: 0;
    overflow: hidden;
}

.satellite-card__header,
.satellite-card__body,
.satellite-card__footer {
    padding: 0 24px;
}

.satellite-card__header {
    padding-top: 26px;
    padding-bottom: 8px;
}

.satellite-card__body {
    padding-bottom: 24px;
}

.satellite-card__footer {
    padding-top: 18px;
    padding-bottom: 24px;
    background: linear-gradient(180deg, rgba(37, 99, 235, 0.08) 0%, rgba(37, 99, 235, 0) 100%);
    border-top: 1px solid rgba(148, 163, 184, 0.2);
}

.satellite-card__title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.satellite-card__subtitle {
    font-size: 0.98rem;
    color: #475569;
    margin: 0;
    line-height: 1.5;
}

.satellite-card__subtitle--accent {
    font-weight: 600;
    color: #1d4ed8;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.satellite-map {
    width: 100%;
    height: 520px;
    border-radius: 20px;
    overflow: hidden;
    position: relative;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.04);
}

.satellite-map__status {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    background: rgba(15, 23, 42, 0.58);
    color: #ffffff;
    text-align: center;
    padding: 24px;
    z-index: 10;
    transition: opacity 0.2s ease;
}

.satellite-map__status.is-error {
    background: rgba(220, 38, 38, 0.72);
}

.satellite-map__status[hidden] {
    display: none;
}

.satellite-map__spinner {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 4px solid rgba(255, 255, 255, 0.35);
    border-top-color: #ffffff;
    animation: satellite-spin 0.9s linear infinite;
}

.satellite-map__status.is-error .satellite-map__spinner,
.satellite-map__spinner[hidden] {
    display: none;
}

.satellite-map__status span {
    font-weight: 600;
    font-size: 1rem;
    line-height: 1.4;
}

@keyframes satellite-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.satellite-map .leaflet-control-container .leaflet-top,
.satellite-map .leaflet-control-container .leaflet-bottom {
    margin: 16px;
}

.satellite-map .leaflet-control-zoom a,
.satellite-map .leaflet-control-layers {
    border-radius: 12px !important;
    border: 1px solid rgba(15, 23, 42, 0.15);
    box-shadow: 0 14px 24px rgba(15, 23, 42, 0.15);
}

.satellite-card--legend {
    gap: 18px;
}

.satellite-legend {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.satellite-legend__scale {
    display: flex;
    align-items: stretch;
    gap: 20px;
}

.satellite-legend__gradient {
    width: 40px;
    border-radius: 16px;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
}

.satellite-legend__gradient--ndvi {
    background: linear-gradient(to top, #27272a 0%, #595959 18%, #f5d77c 40%, #7acb5a 70%, #0f5d21 100%);
}

.satellite-legend__gradient--evi {
    background: linear-gradient(to top, #311350 0%, #4338ca 25%, #1d4ed8 45%, #22d3ee 70%, #bbf7d0 100%);
}

.satellite-legend__gradient--ndmi {
    background: linear-gradient(to top, #7f1d1d 0%, #f97316 30%, #facc15 55%, #34d399 80%, #0ea5e9 100%);
}

.satellite-legend__gradient--ndwi {
    background: linear-gradient(to top, #0f5132 0%, #34d399 40%, #e0f2fe 60%, #2563eb 85%, #0b1d51 100%);
}

.satellite-legend__gradient--swir {
    background: linear-gradient(to top, #0f172a 0%, #1e293b 20%, #38bdf8 40%, #f97316 70%, #facc15 100%);
}

.satellite-legend__ticks {
    list-style: none;
    margin: 0;
    padding: 4px 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    font-size: 0.85rem;
    color: #1f2937;
    font-weight: 500;
}

.satellite-legend__ticks--text {
    justify-content: space-around;
}

.satellite-legend__caption {
    font-size: 0.9rem;
    color: #475569;
    margin: 0;
}

.satellite-legend__labels {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
    font-size: 0.9rem;
    color: #1f2937;
}

.satellite-legend__labels li {
    display: flex;
    align-items: center;
    gap: 10px;
}

.satellite-legend__dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.12);
}

.satellite-insight-list,
.satellite-tips {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.satellite-insight-list li,
.satellite-tips li {
    font-size: 0.95rem;
    color: #1f2937;
    line-height: 1.5;
    display: flex;
    gap: 10px;
    align-items: flex-start;
}

.satellite-insight-list li::before {
    content: '\2022';
    color: #2563eb;
    font-weight: 700;
    margin-top: 0.2em;
}

.satellite-tips li::before {
    content: '\2713';
    color: #0f9d58;
    font-weight: 700;
    margin-top: 0.2em;
}

.satellite-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #2563eb;
    font-weight: 600;
    text-decoration: none;
}

.satellite-link:hover {
    text-decoration: underline;
}

.satellite-empty {
    font-size: 0.95rem;
    color: #475569;
    padding: 40px;
    text-align: center;
}

@media (max-width: 1200px) {
    .satellite-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
    .satellite-map {
        height: 460px;
    }
}

@media (max-width: 720px) {
    .satellite-toolbar {
        flex-direction: column;
    }
    .satellite-button--ghost {
        width: 100%;
    }
    .satellite-map {
        height: 420px;
    }
}

@media (max-width: 540px) {
    .satellite-form {
        padding: 16px;
        gap: 14px;
    }
    .satellite-form__field {
        min-width: 0;
    }
    .satellite-card__header,
    .satellite-card__body,
    .satellite-card__footer {
        padding-left: 18px;
        padding-right: 18px;
    }
    .satellite-map {
        height: 360px;
    }
}
