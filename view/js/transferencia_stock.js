/*
 * This file is part of facturacion_base
 * Copyright (C) 2016-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

var form_url = 'index.php?page=editar_transferencia_stock';
var numlineas = 0;

function add_articulo(ref,desc,cantidad)
{
   numlineas = parseInt( $("#numlineas").val() );
   
   desc = Base64.decode(desc);
   $("#lineas_transferencia").append("<tr id=\"linea_"+numlineas+"\">\n\
      <td><input type=\"hidden\" name=\"idlinea_"+numlineas+"\" value=\"-1\"/>\n\
         <input type=\"hidden\" name=\"referencia_"+numlineas+"\" value=\""+ref+"\"/>\n\
         <div class=\"form-control\"><small><a target=\"_blank\" href=\"index.php?page=ventas_articulo&ref="+ref+"\">"+ref+"</a></small></div></td>\n\
      <td><textarea class=\"form-control\" id=\"desc_"+numlineas+"\" name=\"desc_"+numlineas+"\" rows=\"1\">"+desc+"</textarea></td>\n\
      <td><input type=\"number\" step=\"any\" id=\"cantidad_"+numlineas+"\" class=\"form-control text-right\" name=\"cantidad_"+numlineas+
         "\" autocomplete=\"off\" value=\""+cantidad+"\"/></td>\n\
      <td><button class=\"btn btn-sm btn-danger\" type=\"button\" onclick=\"$('#linea_"+numlineas+"').remove();\">\n\
         <span class=\"glyphicon glyphicon-trash\"></span></button></td></tr>");
   numlineas += 1;
   $("#numlineas").val(numlineas);
   
   $("#modal_articulos").modal('hide');
   
   $("#desc_"+(numlineas-1)).select();
   return false;
}

function buscar_articulos()
{
   if(document.f_buscar_articulos.query.value === '')
   {
      $("#nav_articulos").hide();
      $("#search_results").html('');
   }
   else
   {
      $("#nav_articulos").show();
      
      if(form_url !== '')
      {
         $.getJSON(form_url, $("form[name=f_buscar_articulos]").serialize(), function(json) {
            var items = [];
            var insertar = false;
            $.each(json, function(key, val) {
               var descripcion = Base64.encode(val.descripcion);
               var descripcion_visible = val.descripcion;
               if(val.codfamilia)
               {
                  descripcion_visible += ' <span class="label label-default" title="Familia: '+val.codfamilia+'">'
                          +val.codfamilia+'</span>';
               }
               if(val.codfabricante)
               {
                  descripcion_visible += ' <span class="label label-default" title="Fabricante: '+val.codfabricante+'">'
                          +val.codfabricante+'</span>';
               }
               
               var tr_aux = '<tr>';
               if(val.origen > 0)
               {
                  tr_aux = '<tr class="success">'
               }
               var funcion = "add_articulo('"+val.referencia+"','"+descripcion+"','1')";
               
               items.push(tr_aux+"<td><a href=\"#\" onclick=\"return "+funcion+"\">"+val.referencia+'</a> '+descripcion_visible+"</td>\n\
                     <td class=\"text-right\">"+val.origen+"</td>\n\
                     <td class=\"text-right\">"+val.destino+"</td></tr>");
               
               if(val.query == document.f_buscar_articulos.query.value)
               {
                  insertar = true;
               }
            });
            
            if(items.length == 0)
            {
               items.push("<tr><td colspan=\"3\" class=\"warning\">Sin resultados. Usa la pestaña\n\
                              <b>Nuevo</b> para crear uno.</td></tr>");
               insertar = true;
            }
            
            if(insertar)
            {
               $("#search_results").html("<div class=\"table-responsive\"><table class=\"table table-hover\"><thead><tr>\n\
                  <th class=\"text-left\">Referencia + descripción</th>\n\
                  <th class=\"text-right\" width=\"80\">Orígen</th>\n\
                  <th class=\"text-right\" width=\"80\">Destino</th>\n\
                  </tr></thead>"+items.join('')+"</table></div>");
            }
         });
      }
   }
}

function eliminar_transferencia(id)
{
      bootbox.confirm({
         message: '¿Realmente desea eliminar la transferencia '+id+'?',
         title: '<b>Atención</b>',
         callback: function(result) {
            if (result) {
               window.location.href = 'index.php?page=ventas_articulos&delete_transf='+id+'#transferencias';
            }
         }
      });
}

$(document).ready(function() {
   $("#ac_referencia").click(function() {
      $("#modal_articulos").modal('show');
      document.f_buscar_articulos.query.select();
   });
   
   $("#f_buscar_articulos").keyup(function() {
      buscar_articulos();
   });
   
   $("#f_buscar_articulos").submit(function(event) {
      event.preventDefault();
      buscar_articulos();
   });
});
