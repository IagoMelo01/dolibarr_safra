<?php
/*
 * Helper functions to import/export Talhão geometries in KML/KMZ formats.
 */

/**
 * Read KML content from a KML or KMZ uploaded file.
 *
 * @param string $filePath Absolute path to uploaded file
 * @return string|false
 */
function safra_talhao_read_kml_content($filePath)
{
        if (!is_readable($filePath)) {
                return false;
        }

        $kmlContent = false;

        // Try to open as KMZ first (zip archive containing a KML)
        if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($filePath) === true) {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                                $stat = $zip->statIndex($i);
                                if (!$stat || empty($stat['name'])) {
                                        continue;
                                }
                                if (preg_match('/\.kml$/i', $stat['name'])) {
                                        $kmlContent = $zip->getFromIndex($i);
                                        break;
                                }
                        }
                        $zip->close();
                }
        }

        if ($kmlContent === false) {
                $kmlContent = file_get_contents($filePath);
        }

        if ($kmlContent === false) {
                return false;
        }

        return $kmlContent;
}

/**
 * Parse a KML document and extract polygons as Talhão features.
 *
 * @param string $kmlContent Raw KML content
 * @param array  $errors     Filled with parsing errors
 * @return array<int,array<string,mixed>>
 */
function safra_talhao_parse_kml($kmlContent, array &$errors = array())
{
        $errors = array();

        if (trim($kmlContent) === '') {
                $errors[] = 'KML content is empty.';
                return array();
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $options = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;
        if (defined('LIBXML_PARSEHUGE')) {
                $options |= LIBXML_PARSEHUGE;
        }

        $loaded = $dom->loadXML($kmlContent, $options);
        if (!$loaded) {
                foreach (libxml_get_errors() as $xmlError) {
                        $errors[] = trim($xmlError->message);
                }
                libxml_clear_errors();
                libxml_use_internal_errors($previousUseErrors);

                return array();
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        $xpath = new DOMXPath($dom);
        $placemarkNodes = $xpath->query('//*[local-name()="Placemark"]');

        $features = array();
        if ($placemarkNodes === false) {
                return $features;
        }

        /** @var DOMElement $placemark */
        foreach ($placemarkNodes as $placemark) {
                $name = trim($xpath->evaluate('string(./*[local-name()="name"])', $placemark));
                $description = trim($xpath->evaluate('string(./*[local-name()="description"])', $placemark));

                $properties = safra_talhao_kml_extract_properties($xpath, $placemark);
                $polygons = safra_talhao_kml_extract_polygons($xpath, $placemark);

                if (empty($polygons)) {
                        continue;
                }

                $features[] = array(
                        'name' => ($name !== '' ? $name : null),
                        'description' => $description,
                        'polygons' => $polygons,
                        'properties' => $properties,
                );
        }

        return $features;
}

/**
 * Extract polygons from a Placemark node.
 *
 * @param DOMNode $placemark
 * @return array<int,array<int,array{0:float,1:float}>>
 */
function safra_talhao_kml_extract_polygons(DOMXPath $xpath, DOMNode $placemark)
{
        $polygons = array();

        $polygonNodes = $xpath->query('.//*[local-name()="Polygon"]', $placemark);
        if ($polygonNodes === false) {
                return $polygons;
        }

        /** @var DOMElement $polygon */
        foreach ($polygonNodes as $polygon) {
                $coordinateNodes = $xpath->query('./*[local-name()="outerBoundaryIs"]//*[local-name()="coordinates"]', $polygon);
                if ($coordinateNodes === false || $coordinateNodes->length === 0) {
                        $coordinateNodes = $xpath->query('.//*[local-name()="coordinates"]', $polygon);
                }

                if ($coordinateNodes === false) {
                        continue;
                }

                /** @var DOMNode $coordinateNode */
                foreach ($coordinateNodes as $coordinateNode) {
                        $coords = safra_talhao_parse_kml_coordinates($coordinateNode->textContent);
                        if (count($coords) >= 3) {
                                $polygons[] = $coords;
                        }
                }
        }

        return $polygons;
}

/**
 * Extract ExtendedData/SimpleData properties from Placemark.
 *
 * @param DOMNode $placemark
 * @return array<string,string>
 */
function safra_talhao_kml_extract_properties(DOMXPath $xpath, DOMNode $placemark)
{
        $properties = array();

        $dataNodes = $xpath->query('.//*[local-name()="ExtendedData"]/*[local-name()="Data"]', $placemark);
        if ($dataNodes !== false) {
                        /** @var DOMElement $node */
                foreach ($dataNodes as $node) {
                        $name = (string) $node->getAttribute('name');
                        $value = '';

                        foreach ($node->childNodes as $child) {
                                if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->localName) === 'value') {
                                        $value = trim($child->textContent);
                                        break;
                                }
                        }

                        if ($value === '' && $node->hasAttribute('value')) {
                                $value = trim($node->getAttribute('value'));
                        }

                        if ($value === '') {
                                $value = trim($node->textContent);
                        }

                        if ($name !== '') {
                                $properties[$name] = $value;
                        }
                }
        }

        $simpleDataNodes = $xpath->query('.//*[local-name()="SimpleData"]', $placemark);
        if ($simpleDataNodes !== false) {
                /** @var DOMElement $node */
                foreach ($simpleDataNodes as $node) {
                        $name = (string) $node->getAttribute('name');
                        $value = trim($node->textContent);
                        if ($name !== '') {
                                $properties[$name] = $value;
                        }
                }
        }

        return $properties;
}

/**
 * Parse a string of KML coordinates (lon,lat[,alt])
 *
 * @param string $text
 * @return array<int,array{0:float,1:float}>
 */
function safra_talhao_parse_kml_coordinates($text)
{
        $coords = array();
        $parts = preg_split('/\s+/', trim((string) $text));
        foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                        continue;
                }
                $values = explode(',', $part);
                if (count($values) < 2) {
                        continue;
                }
                $lon = (float) trim($values[0]);
                $lat = (float) trim($values[1]);
                $coords[] = array($lon, $lat);
        }

        $coords = safra_talhao_close_ring($coords);

        return $coords;
}

/**
 * Ensure a polygon ring is closed.
 *
 * @param array<int,array{0:float,1:float}> $coords
 * @return array<int,array{0:float,1:float}>
 */
function safra_talhao_close_ring(array $coords)
{
        if (count($coords) < 3) {
                return $coords;
        }

        $first = $coords[0];
        $last = end($coords);
        if (!safra_talhao_points_equal($first, $last)) {
                $coords[] = $first;
        }

        return $coords;
}

/**
 * Compare two [lon,lat] points with a tolerance.
 *
 * @param array{0:float,1:float} $a
 * @param array{0:float,1:float} $b
 * @return bool
 */
function safra_talhao_points_equal(array $a, array $b)
{
        return (abs($a[0] - $b[0]) < 1e-9) && (abs($a[1] - $b[1]) < 1e-9);
}

/**
 * Compute area of a polygon (ring) in square meters using an equirectangular projection.
 *
 * @param array<int,array{0:float,1:float}> $polygon
 * @return float
 */
function safra_talhao_polygon_area_m2(array $polygon)
{
        $polygon = safra_talhao_close_ring($polygon);
        $count = count($polygon);
        if ($count < 4) {
                return 0.0;
        }

        $earthRadius = 6378137.0;

        $latSum = 0.0;
        for ($i = 0; $i < $count - 1; $i++) {
                $latSum += deg2rad($polygon[$i][1]);
        }
        $latRef = ($count > 1) ? $latSum / ($count - 1) : 0.0;

        $area = 0.0;
        for ($i = 0; $i < $count - 1; $i++) {
                $p1 = $polygon[$i];
                $p2 = $polygon[$i + 1];

                $x1 = deg2rad($p1[0]) * $earthRadius * cos($latRef);
                $y1 = deg2rad($p1[1]) * $earthRadius;
                $x2 = deg2rad($p2[0]) * $earthRadius * cos($latRef);
                $y2 = deg2rad($p2[1]) * $earthRadius;

                $area += ($x1 * $y2) - ($x2 * $y1);
        }

        return abs($area) / 2.0;
}

/**
 * Compute the area of multiple polygons in hectares.
 *
 * @param array<int,array<int,array{0:float,1:float}>> $polygons
 * @return float
 */
function safra_talhao_polygons_area_ha(array $polygons)
{
        $area = 0.0;
        foreach ($polygons as $polygon) {
                $area += safra_talhao_polygon_area_m2($polygon);
        }

        return $area / 10000.0;
}

/**
 * Compute bounding box of polygons.
 *
 * @param array<int,array<int,array{0:float,1:float}>> $polygons
 * @return array{minLon:float,minLat:float,maxLon:float,maxLat:float}|null
 */
function safra_talhao_polygons_bbox(array $polygons)
{
        $minLon = $minLat = $maxLon = $maxLat = null;

        foreach ($polygons as $polygon) {
                foreach ($polygon as $point) {
                        $lon = (float) $point[0];
                        $lat = (float) $point[1];

                        $minLon = ($minLon === null) ? $lon : min($minLon, $lon);
                        $maxLon = ($maxLon === null) ? $lon : max($maxLon, $lon);
                        $minLat = ($minLat === null) ? $lat : min($minLat, $lat);
                        $maxLat = ($maxLat === null) ? $lat : max($maxLat, $lat);
                }
        }

        if ($minLon === null || $minLat === null || $maxLon === null || $maxLat === null) {
                return null;
        }

        return array(
                'minLon' => $minLon,
                'minLat' => $minLat,
                'maxLon' => $maxLon,
                'maxLat' => $maxLat,
        );
}

/**
 * Format bounding box as Leaflet's "west,south,east,north" string.
 *
 * @param array{minLon:float,minLat:float,maxLon:float,maxLat:float} $bbox
 * @return string
 */
function safra_talhao_format_bbox(array $bbox)
{
        return sprintf('%.8F,%.8F,%.8F,%.8F', $bbox['minLon'], $bbox['minLat'], $bbox['maxLon'], $bbox['maxLat']);
}

/**
 * Compute geometric center from bbox.
 *
 * @param array{minLon:float,minLat:float,maxLon:float,maxLat:float} $bbox
 * @return array{lat:float,lon:float}
 */
function safra_talhao_center_from_bbox(array $bbox)
{
        return array(
                'lat' => ($bbox['minLat'] + $bbox['maxLat']) / 2.0,
                'lon' => ($bbox['minLon'] + $bbox['maxLon']) / 2.0,
        );
}

/**
 * Format center lat/lon pair as string.
 *
 * @param array{lat:float,lon:float} $center
 * @return string
 */
function safra_talhao_format_center(array $center)
{
        return sprintf('%.8F,%.8F', $center['lat'], $center['lon']);
}

/**
 * Build a GeoJSON Feature string from polygons.
 *
 * @param array<int,array<int,array{0:float,1:float}>> $polygons
 * @param array<string,mixed> $properties
 * @return string
 */
function safra_talhao_polygons_to_geojson(array $polygons, array $properties = array())
{
        $geometry = array();
        if (count($polygons) === 1) {
                $geometry = array(
                        'type' => 'Polygon',
                        'coordinates' => array($polygons[0]),
                );
        } else {
                $multi = array();
                foreach ($polygons as $polygon) {
                        $multi[] = array($polygon);
                }
                $geometry = array(
                        'type' => 'MultiPolygon',
                        'coordinates' => $multi,
                );
        }

        $feature = array(
                'type' => 'Feature',
                'geometry' => $geometry,
                'properties' => $properties,
        );

        return json_encode($feature, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Build WKT string from polygons.
 *
 * @param array<int,array<int,array{0:float,1:float}>> $polygons
 * @return string
 */
function safra_talhao_polygons_to_wkt(array $polygons)
{
        if (empty($polygons)) {
                        return '';
        }

        $parts = array();
        foreach ($polygons as $polygon) {
                $ring = safra_talhao_close_ring($polygon);
                $coords = array();
                foreach ($ring as $point) {
                        $coords[] = sprintf('%.8F %.8F', $point[0], $point[1]);
                }
                $parts[] = '(('.implode(', ', $coords).'))';
        }

        if (count($parts) === 1) {
                return 'POLYGON'.$parts[0];
        }

        return 'MULTIPOLYGON('.implode(',', $parts).')';
}

/**
 * Extract polygons from a GeoJSON string (Feature or Geometry).
 *
 * @param string $geojson
 * @return array<int,array<int,array{0:float,1:float}>>
 */
function safra_talhao_extract_polygons_from_geojson($geojson)
{
        if (!is_string($geojson) || trim($geojson) === '') {
                return array();
        }

        $decoded = json_decode($geojson, true);
        if (!is_array($decoded)) {
                return array();
        }

        if (isset($decoded['type']) && $decoded['type'] === 'Feature') {
                $geometry = isset($decoded['geometry']) ? $decoded['geometry'] : null;
        } else {
                $geometry = $decoded;
        }

        return safra_talhao_extract_polygons_from_geometry($geometry);
}

/**
 * Extract polygons from a GeoJSON geometry array.
 *
 * @param array|null $geometry
 * @return array<int,array<int,array{0:float,1:float}>>
 */
function safra_talhao_extract_polygons_from_geometry($geometry)
{
        if (!is_array($geometry) || empty($geometry['type'])) {
                return array();
        }

        $type = strtoupper($geometry['type']);
        $coordinates = isset($geometry['coordinates']) ? $geometry['coordinates'] : array();

        if ($type === 'POLYGON') {
                if (!is_array($coordinates) || empty($coordinates)) {
                        return array();
                }
                $ring = safra_talhao_close_ring(array_map('safra_talhao_normalize_point', $coordinates[0]));
                return (count($ring) >= 3) ? array($ring) : array();
        }

        if ($type === 'MULTIPOLYGON') {
                $polygons = array();
                foreach ($coordinates as $polygon) {
                        if (!is_array($polygon) || empty($polygon)) {
                                continue;
                        }
                        $ring = safra_talhao_close_ring(array_map('safra_talhao_normalize_point', $polygon[0]));
                        if (count($ring) >= 3) {
                                $polygons[] = $ring;
                        }
                }
                return $polygons;
        }

        if ($type === 'GEOMETRYCOLLECTION' && !empty($geometry['geometries'])) {
                $polygons = array();
                foreach ($geometry['geometries'] as $geom) {
                        $polygons = array_merge($polygons, safra_talhao_extract_polygons_from_geometry($geom));
                }
                return $polygons;
        }

        return array();
}

/**
 * Normalise GeoJSON coordinate into [lon, lat] float pair.
 *
 * @param mixed $point
 * @return array{0:float,1:float}
 */
function safra_talhao_normalize_point($point)
{
        if (is_array($point) && count($point) >= 2) {
                return array((float) $point[0], (float) $point[1]);
        }

        return array(0.0, 0.0);
}

/**
 * Extract polygons from a WKT string produced by this module.
 *
 * @param string $wkt
 * @return array<int,array<int,array{0:float,1:float}>>
 */
function safra_talhao_extract_polygons_from_wkt($wkt)
{
        if (!is_string($wkt)) {
                return array();
        }

        $wkt = trim($wkt);
        if ($wkt === '') {
                return array();
        }

        $wktUpper = strtoupper($wkt);
        if (strpos($wktUpper, 'POLYGON') !== 0 && strpos($wktUpper, 'MULTIPOLYGON') !== 0) {
                return array();
        }

        $inner = preg_replace('/^[^(]*\(\(/', '', $wkt);
        $inner = preg_replace('/\)\)[^)]*$/', '', $inner);
        if ($inner === null) {
                return array();
        }

        $parts = preg_split('/\)\)\s*,\s*\(\(/', $inner);
        if ($parts === false) {
                $parts = array($inner);
        }

        $polygons = array();
        foreach ($parts as $part) {
                $part = trim($part, '() ');
                if ($part === '') {
                        continue;
                }
                $points = safra_talhao_parse_wkt_coordinates($part);
                if (count($points) >= 3) {
                        $polygons[] = safra_talhao_close_ring($points);
                }
        }

        return $polygons;
}

/**
 * Parse list of "lon lat" coordinates from WKT.
 *
 * @param string $part
 * @return array<int,array{0:float,1:float}>
 */
function safra_talhao_parse_wkt_coordinates($part)
{
        $points = array();
        $pairs = preg_split('/\s*,\s*/', trim($part));
        if ($pairs === false) {
                return array();
        }

        foreach ($pairs as $pair) {
                $pair = trim($pair);
                if ($pair === '') {
                        continue;
                }
                $coords = preg_split('/\s+/', $pair);
                if ($coords === false || count($coords) < 2) {
                        continue;
                }
                $lon = (float) $coords[0];
                $lat = (float) $coords[1];
                $points[] = array($lon, $lat);
        }

        return $points;
}

/**
 * Build a KML document from prepared features.
 *
 * Each feature must contain keys: name, description, polygons (array of polygon rings), properties (optional key/value pairs).
 *
 * @param array<int,array<string,mixed>> $features
 * @return string
 */
function safra_talhao_build_kml(array $features)
{
        $kml = '<?xml version="1.0" encoding="UTF-8"?>';
        $kml .= '<kml xmlns="http://www.opengis.net/kml/2.2"><Document>';

        foreach ($features as $feature) {
                $name = isset($feature['name']) ? $feature['name'] : '';
                $description = isset($feature['description']) ? $feature['description'] : '';
                $polygons = isset($feature['polygons']) ? $feature['polygons'] : array();
                $properties = isset($feature['properties']) ? $feature['properties'] : array();

                if (empty($polygons)) {
                        continue;
                }

                $kml .= '<Placemark>';
                if ($name !== '') {
                        $kml .= '<name>'.htmlspecialchars($name, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</name>';
                }
                if ($description !== '') {
                        $kml .= '<description><![CDATA['.$description.']]></description>';
                }
                if (!empty($properties) && is_array($properties)) {
                        $kml .= '<ExtendedData>';
                        foreach ($properties as $key => $value) {
                                $kml .= '<Data name="'.htmlspecialchars((string) $key, ENT_XML1 | ENT_COMPAT, 'UTF-8').'">';
                                $kml .= '<value>'.htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</value>';
                                $kml .= '</Data>';
                        }
                        $kml .= '</ExtendedData>';
                }

                if (count($polygons) > 1) {
                        $kml .= '<MultiGeometry>';
                        foreach ($polygons as $polygon) {
                                $kml .= safra_talhao_build_kml_polygon($polygon);
                        }
                        $kml .= '</MultiGeometry>';
                } else {
                        $kml .= safra_talhao_build_kml_polygon($polygons[0]);
                }

                $kml .= '</Placemark>';
        }

        $kml .= '</Document></kml>';

        return $kml;
}

/**
 * Build the KML fragment for a single polygon ring.
 *
 * @param array<int,array{0:float,1:float}> $polygon
 * @return string
 */
function safra_talhao_build_kml_polygon(array $polygon)
{
        $polygon = safra_talhao_close_ring($polygon);
        if (count($polygon) < 4) {
                return '';
        }

        $coordinates = array();
        foreach ($polygon as $point) {
                $coordinates[] = sprintf('%.8F,%.8F,0', $point[0], $point[1]);
        }

        return '<Polygon><outerBoundaryIs><LinearRing><coordinates>'.implode(' ', $coordinates).'</coordinates></LinearRing></outerBoundaryIs></Polygon>';
}

/**
 * Wrap KML into a KMZ archive.
 *
 * @param string $kml
 * @return string|false
 */
function safra_talhao_wrap_kml_to_kmz($kml)
{
        if (!class_exists('ZipArchive')) {
                return false;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'safra_kmz_');
        if ($tmpFile === false) {
                return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                @unlink($tmpFile);
                return false;
        }

        $zip->addFromString('talhoes.kml', $kml);
        $zip->close();

        $data = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $data;
}
