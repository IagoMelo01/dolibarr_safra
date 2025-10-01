<?php
/*
 * Copyright (C) 2025 SuperAdmin
 */

if (!defined('NOTOKENRENEWAL') && !empty($user) && !empty($user->id)) {
    // nothing
}

$cancel = GETPOST('cancel', 'alpha');
if ($cancel) {
    if ($action === 'create') {
        header('Location: '.dol_buildpath('/safra/produto_formulado/list.php', 1));
        exit;
    }
    $action = 'view';
}

if (!function_exists('safra_validate_token')) {
    /**
     * Validate CSRF token for mutating actions.
     *
     * @param string $token Token from request
     */
    function safra_validate_token($token)
    {
        if (empty($token) || empty($_SESSION['newtoken']) || !hash_equals($_SESSION['newtoken'], $token)) {
            accessforbidden('InvalidToken');
        }
    }
}

if (isset($safra_produto_schema_ok) && !$safra_produto_schema_ok) {
    return;
}

if ($action === 'add' && $user->rights->safra->produtoformulado->write) {
    safra_validate_token(GETPOST('token', 'alpha'));

    $object->ref = trim(GETPOST('ref', 'alphanohtml'));
    $object->label = trim(GETPOST('label', 'alphanohtml'));
    $object->description = GETPOST('description', 'restricthtml');
    $object->status = GETPOSTINT('status');
    $cultureIds = GETPOST('fk_culturas', 'array:int');
    $cultureDose = trim(GETPOST('dose_label_cultura', 'alphanohtml'));
    if ($cultureDose === '') {
        $cultureDose = trim(GETPOST('dose_label', 'alphanohtml'));
    }
    $cultureObservation = trim(GETPOST('observacao_cultura', 'alphanohtml'));
    if ($cultureObservation === '') {
        $cultureObservation = trim(GETPOST('observacao', 'alphanohtml'));
    }
    $pragaIds = GETPOST('fk_pragas', 'array:int');
    $pragaObservation = trim(GETPOST('observacao_praga', 'alphanohtml'));
    if ($pragaObservation === '') {
        $pragaObservation = trim(GETPOST('observacao', 'alphanohtml'));
    }

    $error = 0;
    if ($object->ref === '') {
        $error++;
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Ref')), null, 'errors');
    }
    if ($object->label === '') {
        $error++;
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Label')), null, 'errors');
    }

    if (!$error) {
        $db->begin();
        $result = $object->create($user);
        if ($result > 0) {
            $linkError = 0;
            if (!empty($cultureIds)) {
                foreach ($cultureIds as $cultureId) {
                    $res = $object->addCulture((int) $cultureId, $cultureDose !== '' ? $cultureDose : null, $cultureObservation !== '' ? $cultureObservation : null);
                    if ($res < 0) {
                        $linkError++;
                        break;
                    }
                }
            }
            if (!$linkError && !empty($pragaIds)) {
                foreach ($pragaIds as $pragaId) {
                    $res = $object->addPraga((int) $pragaId, $pragaObservation !== '' ? $pragaObservation : null);
                    if ($res < 0) {
                        $linkError++;
                        break;
                    }
                }
            }

            if (!$linkError) {
                $db->commit();
                header('Location: '.dol_buildpath('/safra/produto_formulado/card.php', 1).'?id='.(int) $object->id);
                exit;
            }

            $db->rollback();
            setEventMessages($object->error ?: $langs->trans('ErrorProdutoFormuladoLinkSave'), $object->errors, 'errors');
        } else {
            $db->rollback();
            setEventMessages($object->error, $object->errors, 'errors');
        }
        $action = 'create';
    } else {
        $action = 'create';
    }
}

if ($action === 'update' && $user->rights->safra->produtoformulado->write) {
    safra_validate_token(GETPOST('token', 'alpha'));

    $object->ref = trim(GETPOST('ref', 'alphanohtml'));
    $object->label = trim(GETPOST('label', 'alphanohtml'));
    $object->description = GETPOST('description', 'restricthtml');
    $object->status = GETPOSTINT('status');

    $error = 0;
    if ($object->ref === '') {
        $error++;
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Ref')), null, 'errors');
    }
    if ($object->label === '') {
        $error++;
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Label')), null, 'errors');
    }

    if (!$error) {
        $db->begin();
        $result = $object->update($user);
        if ($result > 0) {
            $db->commit();
            header('Location: '.dol_buildpath('/safra/produto_formulado/card.php', 1).'?id='.(int) $object->id);
            exit;
        }
        $db->rollback();
        setEventMessages($object->error, $object->errors, 'errors');
        $action = 'edit';
    } else {
        $action = 'edit';
    }
}

if ($action === 'confirm_delete' && $user->rights->safra->produtoformulado->delete) {
    safra_validate_token(GETPOST('token', 'alpha'));

    if (GETPOST('confirm', 'alpha') === 'yes') {
        $db->begin();
        $result = $object->delete($user);
        if ($result > 0) {
            $db->commit();
            header('Location: '.dol_buildpath('/safra/produto_formulado/list.php', 1));
            exit;
        }
        $db->rollback();
        setEventMessages($object->error, $object->errors, 'errors');
    }
    $action = 'view';
}

if ($action === 'addculture' && $user->rights->safra->produtoformulado->write) {
    safra_validate_token(GETPOST('token', 'alpha'));

    $cultureIds = GETPOST('fk_culturas', 'array:int');
    $doseLabel = trim(GETPOST('dose_label_cultura', 'alphanohtml'));
    if ($doseLabel === '') {
        $doseLabel = trim(GETPOST('dose_label', 'alphanohtml'));
    }
    $observacao = trim(GETPOST('observacao_cultura', 'alphanohtml'));
    if ($observacao === '') {
        $observacao = trim(GETPOST('observacao', 'alphanohtml'));
    }

    if (empty($cultureIds)) {
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Cultura')), null, 'errors');
    } else {
        $db->begin();
        $error = 0;
        foreach ($cultureIds as $cultureId) {
            $res = $object->addCulture((int) $cultureId, $doseLabel !== '' ? $doseLabel : null, $observacao !== '' ? $observacao : null);
            if ($res < 0) {
                $error++;
                break;
            }
        }
        if (!$error) {
            $db->commit();
            setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
        } else {
            $db->rollback();
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }
    header('Location: '.dol_buildpath('/safra/produto_formulado/card.php', 1).'?id='.(int) $object->id.'&tab=culturas');
    exit;
}

if ($action === 'removeculture' && $user->rights->safra->produtoformulado->write) {
    safra_validate_token(GETPOST('token', 'alpha'));

    $cultureId = GETPOSTINT('fk_cultura');
    if ($cultureId > 0) {
        $db->begin();
        $res = $object->removeCulture($cultureId);
        if ($res > 0) {
            $db->commit();
            setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
        } else {
            $db->rollback();
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }
    header('Location: '.dol_buildpath('/safra/produto_formulado/card.php', 1).'?id='.(int) $object->id.'&tab=culturas');
    exit;
}

if ($action === 'addpraga' && $user->rights->safra->produtoformulado->write) {
    safra_validate_token(GETPOST('token', 'alpha'));

    $pragaIds = GETPOST('fk_pragas', 'array:int');
    $observacao = trim(GETPOST('observacao_praga', 'alphanohtml'));
    if ($observacao === '') {
        $observacao = trim(GETPOST('observacao', 'alphanohtml'));
    }

    if (empty($pragaIds)) {
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Praga')), null, 'errors');
    } else {
        $db->begin();
        $error = 0;
        foreach ($pragaIds as $pragaId) {
            $res = $object->addPraga((int) $pragaId, $observacao !== '' ? $observacao : null);
            if ($res < 0) {
                $error++;
                break;
            }
        }
        if (!$error) {
            $db->commit();
            setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
        } else {
            $db->rollback();
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }
    header('Location: '.dol_buildpath('/safra/produto_formulado/card.php', 1).'?id='.(int) $object->id.'&tab=pragas');
    exit;
}

if ($action === 'removepraga' && $user->rights->safra->produtoformulado->write) {
    safra_validate_token(GETPOST('token', 'alpha'));

    $pragaId = GETPOSTINT('fk_praga');
    if ($pragaId > 0) {
        $db->begin();
        $res = $object->removePraga($pragaId);
        if ($res > 0) {
            $db->commit();
            setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
        } else {
            $db->rollback();
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }
    header('Location: '.dol_buildpath('/safra/produto_formulado/card.php', 1).'?id='.(int) $object->id.'&tab=pragas');
    exit;
}
