<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2015-2020 Carlos Garcia Gomez <neorazorx@gmail.com>
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
require_once 'base/fs_settings.php';

/**
 * Description of base_wizard
 *
 * @author Carlos Garcia Gomez
 */
class base_wizard extends fs_controller
{

    public $almacen;
    public $bad_password;
    public $divisa;
    public $ejercicio;
    public $forma_pago;
    private $fs_var;
    public $irpf;
    public $pais;
    public $recargar;
    public $serie;
    public $settings;
    public $step;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Asistente de instalación', 'admin', FALSE, FALSE);
    }

    public function country_plugins()
    {
        return [
            ['name' => 'Argentina', 'id' => 88],
            ['name' => 'Chile', 'id' => 90],
            ['name' => 'Colombia', 'id' => 93],
            ['name' => 'Ecuador', 'id' => 91],
            ['name' => 'Panamá', 'id' => 89],
            ['name' => 'Perú', 'id' => 92],
            ['name' => 'Rep. Dominicana', 'id' => 95],
            ['name' => 'Venezuela', 'id' => 173],
        ];
    }

    protected function private_core()
    {
        $this->recargar = FALSE;

        if (floatval($this->version()) >= 2017.090) {
            $this->private_core2();
        } else {
            $this->template = 'alternative/base_wizard_update';
        }
    }

    private function private_core2()
    {
        $this->check_menu();

        $this->almacen = new almacen();
        $this->bad_password = FALSE;
        $this->divisa = new divisa();
        $this->ejercicio = new ejercicio();
        $this->forma_pago = new forma_pago();
        $this->fs_var = new fs_var();
        $this->irpf = 0;
        $this->pais = new pais();
        $this->serie = new serie();
        $this->settings = new fs_settings();

        /// ¿Hay errores? Usa informes > Errores
        if ($this->get_errors()) {
            $this->new_message('Puedes solucionar la mayoría de errores en la base de datos ejecutando el '
                . '<a href="index.php?page=informe_errores" target="_blank">informe de errores</a> '
                . 'sobre las tablas.');
        }

        if ($this->user->password == sha1('admin')) {
            $this->bad_password = TRUE;
        }

        $this->step = $this->fs_var->simple_get('install_step');
        if ($this->step < 2 || isset($_GET['restart'])) {
            $this->step = 2;
        }

        if (FS_DEMO) {
            $this->new_advice('En el modo demo no se pueden hacer cambios en esta página.');
            $this->new_advice('Si te gusta FacturaScripts y quieres saber más, consulta la '
                . '<a href="https://facturascripts.com/doc/2">documentación</a>.');
        } else if (isset($_POST['nombrecorto'])) {
            /// guardamos los datos de la empresa
            $this->save_step1();
        } else if (isset($_POST['coddivisa'])) {
            $this->save_step2();
        } else if (isset($_POST['codejercicio'])) {
            $this->save_step3();
        }

        /// cargamos/guardamos el IRPF
        $this->save_irpf();
    }

    /**
     * Cargamos el menú en la base de datos, pero en varias pasadas.
     */
    private function check_menu()
    {
        if (!file_exists(__DIR__)) {
            $this->new_error_msg('No se encuentra el directorio ' . __DIR__);
            return;
        }

        /// leemos todos los controladores del plugin
        $max = 25;
        foreach (fs_file_manager::scan_files(__DIR__, 'php') as $f) {
            if ($f == __CLASS__ . '.php') {
                continue;
            }

            /// obtenemos el nombre
            $page_name = substr($f, 0, -4);

            /// lo buscamos en el menú
            $encontrado = FALSE;
            foreach ($this->menu as $m) {
                if ($m->name == $page_name) {
                    $encontrado = TRUE;
                    break;
                }
            }

            if ($encontrado) {
                continue;
            }

            require_once __DIR__ . '/' . $f;
            $new_fsc = new $page_name();
            if (!$new_fsc->page->save()) {
                $this->new_error_msg("Imposible guardar la página " . $page_name);
            }

            unset($new_fsc);

            if ($max > 0) {
                $max--;
            } else if (!$this->get_errors()) {
                $this->recargar = TRUE;
                $this->new_message('Instalando el menú... &nbsp; <i class="fa fa-refresh fa-spin"></i>');
                break;
            }
        }

        $this->load_menu(TRUE);
    }

    private function save_irpf()
    {
        foreach ($this->serie->all() as $serie) {
            if ($serie->codserie == $this->empresa->codserie) {
                if (isset($_POST['irpf_serie'])) {
                    $serie->irpf = floatval($_POST['irpf_serie']);
                    $serie->save();
                }

                $this->irpf = $serie->irpf;
                break;
            }
        }
    }

    private function save_step1()
    {
        $this->empresa->nombre = $_POST['nombre'];
        $this->empresa->nombrecorto = $_POST['nombrecorto'];
        $this->empresa->cifnif = $_POST['cifnif'];
        $this->empresa->administrador = $_POST['administrador'];
        $this->empresa->codpais = $_POST['codpais'];
        $this->empresa->provincia = $_POST['provincia'];
        $this->empresa->ciudad = $_POST['ciudad'];
        $this->empresa->direccion = $_POST['direccion'];
        $this->empresa->apartado = $_POST['apartado'];
        $this->empresa->codpostal = $_POST['codpostal'];
        $this->empresa->telefono = $_POST['telefono'];
        $this->empresa->fax = $_POST['fax'];
        $this->empresa->web = $_POST['web'];

        $continuar = TRUE;
        if (isset($_POST['npassword']) && $_POST['npassword'] != '') {
            if ($_POST['npassword'] == $_POST['npassword2']) {
                $this->user->set_password($_POST['npassword']);
                $this->user->save();
            } else {
                $this->new_error_msg('Las contraseñas no coinciden.');
                $continuar = FALSE;
            }
        }

        if (!$continuar) {
            /// no hacemos nada
        } else if ($this->empresa->save()) {
            $this->new_message('Datos guardados correctamente.');

            /// avanzamos el asistente
            $this->step = 3;

            if ($this->empresa->codpais == 'ESP' || $this->empresa->codpais == 'ES') {
                /// si es España nos podemos ahorrar un paso
                $this->empresa->coddivisa = 'EUR';
                $this->empresa->save();
                $this->step = 4;
            }

            $this->fs_var->simple_save('install_step', $this->step);
        } else {
            $this->new_error_msg('Error al guardar los datos.');
        }
    }

    private function save_step2()
    {
        $this->empresa->coddivisa = $_POST['coddivisa'];

        if ($this->empresa->save()) {
            foreach ($GLOBALS['config2'] as $i => $value) {
                if (isset($_POST[$i])) {
                    $GLOBALS['config2'][$i] = $_POST[$i];
                }
            }

            $this->settings->save();
            $this->new_message('Datos guardados correctamente.');

            /// avanzamos el asistente
            $this->step = 4;
            $this->fs_var->simple_save('install_step', $this->step);
        } else {
            $this->new_error_msg('Error al guardar los datos.');
        }
    }

    private function save_step3()
    {
        $this->empresa->contintegrada = isset($_POST['contintegrada']);
        $this->empresa->codejercicio = $_POST['codejercicio'];
        $this->empresa->codserie = $_POST['codserie'];
        $this->empresa->codpago = $_POST['codpago'];
        $this->empresa->codalmacen = $_POST['codalmacen'];
        $this->empresa->recequivalencia = isset($_POST['recequivalencia']);

        if ($this->empresa->save()) {
            /// guardamos las opciones por defecto de almacén y forma de pago
            $this->save_codalmacen($_POST['codalmacen']);
            $this->save_codpago($_POST['codpago']);

            foreach ($GLOBALS['config2'] as $i => $value) {
                if (isset($_POST[$i])) {
                    $GLOBALS['config2'][$i] = $_POST[$i];
                }
            }

            $this->settings->save();
            $this->new_message('Datos guardados correctamente.');

            /// avanzamos el asistente
            $this->step = 5;
            $this->fs_var->simple_save('install_step', $this->step);
        } else {
            $this->new_error_msg('Error al guardar los datos.');
        }
    }
}
