<?php

class FiscalRuleSet
{
    /** @var DoliDB */
    protected $db;

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    public function resolveItemTaxes(array &$line)
    {
        if (empty($line['cfop'])) {
            $line['cfop'] = '5102';
        }
        if (empty($line['ncm'])) {
            $line['ncm'] = '00000000';
        }
        if (empty($line['cst_csosn'])) {
            $line['cst_csosn'] = '102';
        }
        if (empty($line['descricao']) && !empty($line['descricao_produto'])) {
            $line['descricao'] = $line['descricao_produto'];
        }
        if (empty($line['descricao'])) {
            throw new Exception('Descrição do item é obrigatória');
        }
    }
}
