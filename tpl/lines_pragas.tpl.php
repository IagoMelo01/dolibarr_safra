<?php
/** @var SafraProdutoFormulado $object */
/** @var array $pragas */

global $langs, $user, $token;

echo '<div class="div-table-responsive">';
echo '<table class="noborder centpercent">';
echo '<tr class="liste_titre">';
echo '<th>'.$langs->trans('Ref').'</th>';
echo '<th>'.$langs->trans('Label').'</th>';
echo '<th>'.$langs->trans('ScientificName').'</th>';
echo '<th>'.$langs->trans('Observation').'</th>';
echo '<th class="center">'.$langs->trans('Actions').'</th>';
echo '</tr>';

if (empty($pragas)) {
    echo '<tr class="oddeven"><td colspan="5" class="center">'.$langs->trans('NoRecordFound').'</td></tr>';
} else {
    foreach ($pragas as $line) {
        echo '<tr class="oddeven">';
        echo '<td>'.dol_escape_htmltag($line->ref).'</td>';
        echo '<td>'.dol_escape_htmltag($line->label).'</td>';
        echo '<td>'.dol_escape_htmltag($line->label_cientifico).'</td>';
        echo '<td>'.dol_escape_htmltag($line->observacao).'</td>';
        echo '<td class="center">';
        if ($user->rights->safra->produtoformulado->write) {
            echo '<form method="POST" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'" class="inline-block">';
            echo '<input type="hidden" name="token" value="'.$token.'">';
            echo '<input type="hidden" name="action" value="removepraga">';
            echo '<input type="hidden" name="id" value="'.(int) $object->id.'">';
            echo '<input type="hidden" name="fk_praga" value="'.(int) $line->fk_praga.'">';
            echo '<button type="submit" class="button-remove button smallpaddingimp">'.$langs->trans('Remove').'</button>';
            echo '</form>';
        }
        echo '</td>';
        echo '</tr>';
    }
}

echo '</table>';
echo '</div>';
