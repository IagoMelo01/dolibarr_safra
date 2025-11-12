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
 * \file        class/SfActivityLine.class.php
 * \ingroup     safra
 * \brief       Wrapper line object exposing the Activity terminology while delegating to the legacy Aplicacao implementation.
 */

require_once __DIR__.'/aplicacao.class.php';

/**
 * Activity line value object used by SfActivity.
 */
class SfActivityLine extends AplicacaoLine
{
    /** @var int */
    public $fk_activity;

    /** @var int */
    public $fk_formulated_product;

    /** @var int */
    public $fk_technical_product;

    /** @var int */
    public $fk_warehouse;

    /** @var int */
    public $movement = 1;

    /**
     * Legacy aliases kept for backwards compatibility.
     *
     * @var int
     */
    public $fk_aplicacao;
    public $fk_produto_formulado;
    public $fk_produtotecnico;
    public $fk_entrepot;

    /**
     * Hydrate an activity line from the legacy Aplicacao representation.
     *
     * @param DoliDB      $db
     * @param array|object $legacyLine
     * @return self
     */
    public static function fromLegacy(DoliDB $db, $legacyLine)
    {
        $line = new self($db);

        if (is_object($legacyLine)) {
            $legacyLine = (array) $legacyLine;
        }

        $line->rowid = empty($legacyLine['rowid']) ? 0 : (int) $legacyLine['rowid'];
        $line->id = $line->rowid;
        $line->fk_activity = empty($legacyLine['fk_aplicacao']) ? 0 : (int) $legacyLine['fk_aplicacao'];
        $line->fk_formulated_product = empty($legacyLine['fk_produto_formulado']) ? 0 : (int) $legacyLine['fk_produto_formulado'];
        $line->fk_technical_product = empty($legacyLine['fk_produtotecnico']) ? 0 : (int) $legacyLine['fk_produtotecnico'];
        $line->fk_warehouse = empty($legacyLine['fk_entrepot']) ? 0 : (int) $legacyLine['fk_entrepot'];
        $line->fk_product = empty($legacyLine['fk_product']) ? 0 : (int) $legacyLine['fk_product'];
        $line->label = isset($legacyLine['label']) ? (string) $legacyLine['label'] : '';
        $line->dose = isset($legacyLine['dose']) ? (float) $legacyLine['dose'] : 0.0;
        $line->dose_unit = isset($legacyLine['dose_unit']) ? (string) $legacyLine['dose_unit'] : '';
        $line->area_ha = isset($legacyLine['area_ha']) ? (float) $legacyLine['area_ha'] : 0.0;
        $line->total_qty = isset($legacyLine['total_qty']) ? (float) $legacyLine['total_qty'] : 0.0;
        $line->note = isset($legacyLine['note']) ? (string) $legacyLine['note'] : '';
        $line->movement = isset($legacyLine['movement']) ? (int) $legacyLine['movement'] : 1;

        $line->syncLegacyAliases();

        return $line;
    }

    /**
     * Hydrate an activity line from a modern array payload.
     *
     * @param DoliDB $db
     * @param array  $data
     * @return self
     */
    public static function fromModernArray(DoliDB $db, array $data)
    {
        $line = new self($db);

        $line->rowid = empty($data['rowid']) ? 0 : (int) $data['rowid'];
        $line->id = $line->rowid;
        $line->fk_activity = empty($data['fk_activity']) ? 0 : (int) $data['fk_activity'];
        $line->fk_formulated_product = empty($data['fk_formulated_product']) ? 0 : (int) $data['fk_formulated_product'];
        $line->fk_technical_product = empty($data['fk_technical_product']) ? 0 : (int) $data['fk_technical_product'];
        $line->fk_warehouse = empty($data['fk_warehouse']) ? 0 : (int) $data['fk_warehouse'];

        if (!empty($data['fk_aplicacao']) && empty($line->fk_activity)) {
            $line->fk_activity = (int) $data['fk_aplicacao'];
        }
        if (!empty($data['fk_produto_formulado']) && empty($line->fk_formulated_product)) {
            $line->fk_formulated_product = (int) $data['fk_produto_formulado'];
        }
        if (!empty($data['fk_produtotecnico']) && empty($line->fk_technical_product)) {
            $line->fk_technical_product = (int) $data['fk_produtotecnico'];
        }
        if (!empty($data['fk_entrepot']) && empty($line->fk_warehouse)) {
            $line->fk_warehouse = (int) $data['fk_entrepot'];
        }

        $line->fk_product = empty($data['fk_product']) ? 0 : (int) $data['fk_product'];
        $line->label = isset($data['label']) ? (string) $data['label'] : '';
        $line->dose = isset($data['dose']) ? (float) $data['dose'] : 0.0;
        $line->dose_unit = isset($data['dose_unit']) ? (string) $data['dose_unit'] : '';
        $line->area_ha = isset($data['area_ha']) ? (float) $data['area_ha'] : 0.0;
        $line->total_qty = isset($data['total_qty']) ? (float) $data['total_qty'] : 0.0;
        $line->note = isset($data['note']) ? (string) $data['note'] : '';
        $line->movement = isset($data['movement']) ? (int) $data['movement'] : 1;

        $line->syncLegacyAliases();

        return $line;
    }

    /**
     * Export the line using the legacy Aplicacao column names.
     *
     * @return array
     */
    public function toLegacyArray()
    {
        $this->syncLegacyAliases();

        return array(
            'rowid' => $this->rowid,
            'fk_aplicacao' => $this->fk_activity,
            'fk_product' => $this->fk_product,
            'fk_produto_formulado' => $this->fk_formulated_product,
            'fk_produtotecnico' => $this->fk_technical_product,
            'fk_entrepot' => $this->fk_warehouse,
            'label' => $this->label,
            'dose' => $this->dose,
            'dose_unit' => $this->dose_unit,
            'area_ha' => $this->area_ha,
            'total_qty' => $this->total_qty,
            'note' => $this->note,
            'movement' => $this->movement,
        );
    }

    /**
     * Synchronise legacy aliases so legacy consumers keep working.
     *
     * @return void
     */
    protected function syncLegacyAliases()
    {
        $this->fk_aplicacao = $this->fk_activity;
        $this->fk_produto_formulado = $this->fk_formulated_product;
        $this->fk_produtotecnico = $this->fk_technical_product;
        $this->fk_entrepot = $this->fk_warehouse;
    }
}
