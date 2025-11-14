<?php
/*
 * Safra activity PDF generator.
 */

dol_include_once('/core/lib/pdf.lib.php');
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

class pdf_safraactivity extends ModelePDF
{
    public $db;
    public $name = 'activity_standard';
    public $description = 'Safra activity summary';
    public $type = 'pdf';

    public function __construct($db)
    {
        global $langs;

        $this->db = $db;
        $this->format = pdf_getFormat();
        $this->page_largeur = $this->format['width'];
        $this->page_hauteur = $this->format['height'];
        $this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
        $this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
        $this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
        $this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

        if (is_object($langs)) {
            $langs->load('safra@safra');
        }
    }

    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $conf, $langs, $hookmanager;

        if (empty($outputlangs)) {
            $outputlangs = $langs;
        }
        $outputlangs->loadLangs(array('safra@safra', 'projects'));

        $object->fetchLines();
        $object->fetch_optionals();

        $dir = $conf->safra->multidir_output[$object->entity] . '/safraactivity/' . dol_sanitizeFileName($object->ref);
        dol_mkdir($dir);
        $file = $dir . '/' . dol_sanitizeFileName($object->ref) . '.pdf';

        $pdf = pdf_getInstance($outputlangs);
        if (class_exists('TCPDF')) {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }
        $pdf->SetTitle($outputlangs->transnoentities('SafraActivityCardTitle', $object->ref));
        $pdf->SetSubject($outputlangs->transnoentities('SafraActivityCardTitle', $object->ref));
        $pdf->SetCreator('Dolibarr');
        $pdf->SetAuthor($outputlangs->convToOutputCharset($GLOBALS['conf']->global->MAIN_INFO_SOCIETE_NOM));
        $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
        $pdf->SetAutoPageBreak(true, $this->marge_basse);
        $pdf->AddPage();

        $pdf->SetFont('', 'B', 14);
        $pdf->MultiCell(0, 6, $outputlangs->transnoentities('SafraActivityCardTitle', $object->ref), 0, 'L');
        $pdf->Ln(4);

        $pdf->SetFont('', '', 10);
        $info = array(
            $outputlangs->transnoentities('Label') . ': ' . $outputlangs->convToOutputCharset($object->label),
            $outputlangs->transnoentities('SafraActivityType') . ': ' . $outputlangs->convToOutputCharset($object->activity_type),
            $outputlangs->transnoentities('SafraActivityDatePlannedStart') . ': ' . ($object->date_planned_start ? dol_print_date($object->date_planned_start, 'dayhour', '', $outputlangs) : '-'),
            $outputlangs->transnoentities('SafraActivityDatePlannedEnd') . ': ' . ($object->date_planned_end ? dol_print_date($object->date_planned_end, 'dayhour', '', $outputlangs) : '-'),
            $outputlangs->transnoentities('SafraActivityPlannedCost') . ': ' . price($object->planned_cost, '', $outputlangs),
            $outputlangs->transnoentities('SafraActivityActualCost') . ': ' . price($object->actual_cost, '', $outputlangs),
        );
        foreach ($info as $line) {
            $pdf->MultiCell(0, 5, $line, 0, 'L');
        }

        $pdf->Ln(4);
        $pdf->SetFont('', 'B', 11);
        $pdf->MultiCell(0, 5, $outputlangs->transnoentities('SafraActivityInputs'), 0, 'L');
        $pdf->Ln(1);
        $pdf->SetFont('', '', 9);

        if (!empty($object->lines)) {
            foreach ($object->lines as $line) {
                $lineText = $outputlangs->convToOutputCharset($line->label ?: '') . ' – ';
                $lineText .= $outputlangs->transnoentities('Qty') . ': ' . price($line->qty, '', $outputlangs);
                if (!empty($line->fk_warehouse)) {
                    $lineText .= ' (' . $outputlangs->transnoentities('Warehouse') . ' #' . $line->fk_warehouse . ')';
                }
                $pdf->MultiCell(0, 4, $lineText, 0, 'L');
            }
        } else {
            $pdf->MultiCell(0, 4, $outputlangs->transnoentities('SafraActivityNoLines'), 0, 'L');
        }

        $pdf->Output($file, 'F');

        if (method_exists($object, 'setDocModel')) {
            $object->setDocModel($this->db, $this->name);
        }
        $object->last_main_doc = basename($file);

        if (is_object($hookmanager)) {
            $hookmanager->executeHooks('afterPDFCreation', array('object' => $object, 'file' => $file, 'module' => 'safraactivity'));
        }

        return 1;
    }
}
