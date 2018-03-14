/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2018  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

function cambiar_pvp()
{
    /// cambiamos coma por punto
    if ($("#pvp").val().search(",") >= 0) {
        $("#pvp").val($("#pvp").val().replace(",", "."));
    }

    var iva = parseFloat($("#iva").val());
    var pvp = parseFloat($("#pvp").val());
    $("#pvpi").val(pvp * (100 + iva) / 100);
    calcular_margen();
}

function cambiar_pvpi()
{
    /// cambiamos coma por punto
    if ($("#pvpi").val().search(",") >= 0) {
        $("#pvpi").val($("#pvpi").val().replace(",", "."));
    }

    var iva = parseFloat($("#iva").val());
    var pvpi = parseFloat($("#pvpi").val());

    var pvp = (100 * pvpi) / (100 + iva);
    $("#pvp").val(pvp);
    calcular_margen();
}

function cambiar_margen()
{
    /// cambiamos coma por punto
    if ($("#coste").val().search(",") >= 0) {
        $("#coste").val($("#coste").val().replace(",", "."));
    }
    if ($("#margen").val().search(",") >= 0) {
        $("#margen").val($("#margen").val().replace(",", "."));
    }

    var iva = parseFloat($("#iva").val());
    var coste = parseFloat($("#coste").val());
    var margen = parseFloat($("#margen").val());

    if (!isNaN(margen) && isFinite(margen)) {
        var pvp = coste * (100 + margen) / 100;
        $("#pvp").val(pvp);
        $("#pvpi").val(pvp * (100 + iva) / 100);
    }
}

function calcular_margen()
{
    var coste = parseFloat($("#coste").val());
    var pvp = parseFloat($("#pvp").val());

    var margen = 0;
    if (coste > 0 && pvp !== 0) {
        margen = (pvp * 100) / coste - 100;
    }

    $("#margen").val(margen);
}

$(document).ready(function () {
    calcular_margen();

    if (window.location.hash.substring(1) == 'precios') {
        $('#tab_articulo a[href="#precios"]').tab('show');
    } else if (window.location.hash.substring(1) == 'stock') {
        $('#tab_articulo a[href="#stock"]').tab('show');
    } else if (window.location.hash.substring(1) == 'atributos') {
        $('#tab_articulo a[href="#atributos"]').tab('show');
    }

    $("#b_imagen").click(function (event) {
        event.preventDefault();
        $("#modal_articulo_imagen").modal('show');
    });
});
