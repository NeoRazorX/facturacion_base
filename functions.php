<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2015-2019 Carlos Garcia Gomez <neorazorx@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
if (!function_exists('fs_tipos_id_fiscal')) {

    /**
     * Devuelve la lista de identificadores fiscales.
     * @return string
     */
    function fs_tipos_id_fiscal()
    {
        return [FS_CIFNIF, 'Pasaporte', 'DNI', 'NIF', 'CIF', 'VAT', 'CUIT'];
    }
}


if (!function_exists('fs_documento_new_numero')) {

    /**
     * 
     * @param fs_db2 $db
     * @param string $table_name
     * @param string $codejercicio
     * @param string $codserie
     * @param string $nombresec
     * @return string
     */
    function fs_documento_new_numero(&$db, $table_name, $codejercicio, $codserie, $nombresec)
    {
        $numero = 1;
        $sec0 = new \secuencia();

        if (FS_NEW_CODIGO == 'eneboo') {
            $sec = $sec0->get_by_params2($codejercicio, $codserie, $nombresec);
            if ($sec) {
                $numero = $sec->valorout;
                $sec->valorout++;
                $sec->save();
            }
        } else {
            $sec = FALSE;
        }

        if (!$sec || $numero <= 1) {
            $sql = "SELECT MAX(" . $db->sql_to_int('numero') . ") as num FROM " . $table_name;
            if (!in_array(FS_NEW_CODIGO, ['NUM', '0-NUM'])) {
                $sql .= " WHERE codejercicio = " . $sec0->var2str($codejercicio) . " AND codserie = " . $sec0->var2str($codserie) . ";";
            }

            $data = $db->select($sql);
            if ($data) {
                $numero = 1 + (int) $data[0]['num'];
            }

            if ($sec) {
                $sec->valorout = 1 + $numero;
                $sec->save();
            }
        }

        return (string) $numero;
    }
}


if (!function_exists('fs_documento_new_codigo')) {

    function fs_documento_new_codigo($tipodoc, $codejercicio, $codserie, $numero, $sufijo = '')
    {
        switch (FS_NEW_CODIGO) {
            case 'eneboo':
                return $codejercicio . str_pad($codserie, 2, '0', STR_PAD_LEFT) . str_pad($numero, 6, '0', STR_PAD_LEFT);

            case '0-NUM':
                return str_pad($numero, 12, '0', STR_PAD_LEFT);

            case 'NUM':
                return (string) $numero;

            case 'SERIE-YY-0-NUM':
                return $codserie . substr($codejercicio, -2) . str_pad($numero, 12, '0', STR_PAD_LEFT);

            case 'SERIE-YY-0-NUM-CORTO':
                if (strlen((string) $numero) < 4) {
                    $numero = str_pad($numero, 4, '0', STR_PAD_LEFT);
                }
                return $codserie . substr($codejercicio, -2) . $numero;
        }

        /// TIPO + EJERCICIO + SERIE + NÚMERO
        return strtoupper(substr($tipodoc, 0, 3)) . $codejercicio . $codserie . $numero . $sufijo;
    }
}

if (!function_exists('fs_huecos_facturas_cliente')) {

    function fs_huecos_facturas_cliente(&$db, $table_name)
    {
        if (in_array(FS_NEW_CODIGO, ['NUM', '0-NUM'])) {
            return fs_huecos_facturas_cliente_continua($db, $table_name);
        }

        $huecolist = [];
        $ejercicio = new \ejercicio();
        $serie = new \serie();
        foreach ($ejercicio->all_abiertos() as $eje) {
            $codserie = '';
            $num = 1;
            $sql = "SELECT codserie," . $db->sql_to_int('numero') . " as numero,fecha,hora FROM "
                . $table_name . " WHERE codejercicio = " . $ejercicio->var2str($eje->codejercicio)
                . " ORDER BY codserie ASC, numero ASC;";

            $data = $db->select($sql);
            if (empty($data)) {
                continue;
            }

            foreach ($data as $d) {
                if ($d['codserie'] != $codserie) {
                    $codserie = $d['codserie'];
                    $num = 1;

                    /// ¿Se ha definido un nº inicial de factura para esta serie y ejercicio?
                    $se = $serie->get($codserie);
                    if ($se && $eje->codejercicio == $se->codejercicio) {
                        $num = $se->numfactura;
                    }
                }

                if (intval($d['numero']) < $num) {
                    /**
                     * El número de la factura es menor que el inicial.
                     * El usuario ha cambiado el número inicial después de hacer
                     * facturas.
                     */
                    continue;
                } else if (intval($d['numero']) == $num) {
                    /// el número es correcto, avanzamos
                    $num++;
                    continue;
                }

                /**
                 * Hemos encontrado un hueco y debemos usar el número y la fecha.
                 * La variable pasos permite dejar de añadir huecos al llegar a 100,
                 * así evitamos agotar la memoria en caso de error grave.
                 */
                $pasos = 0;
                while ($num < intval($d['numero']) && $pasos < 100) {
                    $huecolist[] = [
                        'codigo' => fs_documento_new_codigo(FS_FACTURA, $eje->codejercicio, $codserie, $num),
                        'fecha' => Date('d-m-Y', strtotime($d['fecha'])),
                        'hora' => $d['hora']
                    ];
                    $num++;
                    $pasos++;
                }

                /// avanzamos uno más
                $num++;
            }
        }

        return $huecolist;
    }

    function fs_huecos_facturas_cliente_continua(&$db, $table_name)
    {
        /// número inicial
        $num = 1;
        $sql2 = "SELECT " . $db->sql_to_int('numero') . " as numero FROM " . $table_name . " ORDER BY numero ASC;";
        $data2 = $db->select($sql2);
        if ($data2) {
            $num = max([$num, intval($data2[0]['numero'])]);
        }

        $sql = "SELECT " . $db->sql_to_int('numero') . " as numero,fecha,hora FROM " . $table_name . " ORDER BY numero ASC;";
        $data = $db->select($sql);
        if (empty($data)) {
            return [];
        }

        $huecolist = [];
        foreach ($data as $d) {
            if (intval($d['numero']) < $num) {
                /**
                 * El número de la factura es menor que el inicial.
                 * El usuario ha cambiado el número inicial después de hacer
                 * facturas.
                 */
                continue;
            } else if (intval($d['numero']) == $num) {
                /// el número es correcto, avanzamos
                $num++;
                continue;
            }

            /**
             * Hemos encontrado un hueco y debemos usar el número y la fecha.
             * La variable pasos permite dejar de añadir huecos al llegar a 100,
             * así evitamos agotar la memoria en caso de error grave.
             */
            $pasos = 0;
            while ($num < intval($d['numero']) && $pasos < 100) {
                $huecolist[] = [
                    'codigo' => fs_documento_new_codigo(FS_FACTURA, '', '', $num),
                    'fecha' => Date('d-m-Y', strtotime($d['fecha'])),
                    'hora' => $d['hora']
                ];
                $num++;
                $pasos++;
            }

            /// avanzamos uno más
            $num++;
        }

        return $huecolist;
    }
}


if (!function_exists('remote_printer')) {

    /**
     * Vuelca en la salida estándar el buffer de tickets pendientes de imprimir.
     */
    function remote_printer()
    {
        if (isset($_REQUEST['terminal'])) {
            $t0 = new terminal_caja();
            $terminal = $t0->get($_REQUEST['terminal']);
            if ($terminal) {
                echo $terminal->tickets;

                $terminal->tickets = '';
                $terminal->save();
            } else {
                echo 'ERROR: terminal no encontrado.';
            }
        }
    }
}


if (!function_exists('plantilla_email')) {

    /**
     * Devuelve el texto para un email con las modificaciones oportunas.
     * @param string $tipo
     * @param string $documento
     * @param string $firma
     * @return string
     */
    function plantilla_email($tipo, $documento, $firma)
    {
        /// obtenemos las plantillas
        $fsvar = new fs_var();
        $plantillas = array(
            'mail_factura' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
            'mail_albaran' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
            'mail_pedido' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
            'mail_presupuesto' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
        );
        $plantillas = $fsvar->array_get($plantillas, FALSE);

        if ($tipo == 'albaran') {
            $documento = FS_ALBARAN . ' ' . $documento;
        } else if ($tipo == 'pedido') {
            $documento = FS_PEDIDO . ' ' . $documento;
        } else if ($tipo == 'presupuesto') {
            $documento = FS_PRESUPUESTO . ' ' . $documento;
        } else {
            $documento = $tipo . ' ' . $documento;
        }

        $txt = str_replace('#DOCUMENTO#', $documento, $plantillas['mail_' . $tipo]);
        return str_replace('#FIRMA#', $firma, $txt);
    }
}

if (!function_exists('fs_generar_numero2')) {

    /**
     * Genera y asigna el valor de numero2. Devuelve true si lo asgina.
     * A completar en los plugins interesados.
     * @param object $documento
     * @return boolean
     */
    function fs_generar_numero2(&$documento)
    {
        return false;
    }
}

if (!function_exists('fs_generar_numproveedor')) {

    /**
     * Genera y asigna el valor de numproveedor. Devuelve true si lo asgina.
     * A completar en los plugins interesados.
     * @param object $documento
     * @return boolean
     */
    function fs_generar_numproveedor(&$documento)
    {
        return false;
    }
}

if (!function_exists('fs_documento_post_save')) {

    /**
     * Genera tareas despues que se guarde un documento de venta o de compra
     * En facturacion_base solo devuelve un ok en los plugins por pais
     * se puede agregar procesos adicionales.
     * @param object $documento
     * @return boolean
     */
    function fs_documento_post_save(&$documento)
    {
        return true;
    }
}
