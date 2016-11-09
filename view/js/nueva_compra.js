/*
 * This file is part of FacturaScripts
 * Copyright (C) 2014-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

var numlineas = 0;
var fs_nf0 = 2;
var fs_nf0_art = 2;
var all_impuestos = [];
var all_series = [];
var proveedor = false;
var nueva_compra_url = '';
var kiwimaru_url = '';
var precio_compra = 'coste';
var fin_busqueda1 = true;
var fin_busqueda2 = true;
var siniva = false;
var irpf = 0;
var tiene_recargo = false;

function usar_proveedor(codproveedor)
{
   if(nueva_compra_url !== '')
   {
      $.getJSON(nueva_compra_url, 'datosproveedor='+codproveedor, function(json) {
         proveedor = json;
         document.f_buscar_articulos.codproveedor.value = proveedor.codproveedor;
         if(proveedor.regimeniva == 'Exento')
         {
            irpf = 0;
            for(var j=0; j<numlineas; j++)
            {
               if($("#linea_"+j).length > 0)
               {
                  $("#iva_"+j).val(0);
                  $("#recargo_"+j).val(0);
               }
            }
         }
         recalcular();
      });
   }
}

function usar_serie()
{
   for(var i=0; i<all_series.length; i++)
   {
      if(all_series[i].codserie == $("#codserie").val())
      {
         siniva = all_series[i].siniva;
         irpf = all_series[i].irpf;
         
         for(var j=0; j<numlineas; j++)
         {
            if($("#linea_"+j).length > 0)
            {
               if(siniva)
               {
                  $("#iva_"+j).val(0);
                  $("#recargo_"+j).val(0);
               }
            }
         }
         
         break;
      }
   }
}

function usar_almacen()
{
   document.f_buscar_articulos.codalmacen.value = $("#codalmacen").val();
}

function usar_divisa()
{
   document.f_buscar_articulos.coddivisa.value = $("#coddivisa").val();
}

function recalcular()
{
   var l_uds = 0;
   var l_pvp = 0;
   var l_dto = 0;
   var l_neto = 0;
   var l_iva = 0;
   var l_irpf = 0;
   var l_recargo = 0;
   var neto = 0;
   var total_iva = 0;
   var total_irpf = 0;
   var total_recargo = 0;
   
   for(var i=0; i<numlineas; i++)
   {
      if($("#linea_"+i).length > 0)
      {
         /// cambiamos coma por punto
         if( input_number == 'text' && $("#cantidad_"+i).val().search(",") >= 0 )
         {
            $("#cantidad_"+i).val( $("#cantidad_"+i).val().replace(",",".") );
         }
         if( $("#pvp_"+i).val().search(",") >= 0 )
         {
            $("#pvp_"+i).val( $("#pvp_"+i).val().replace(",",".") );
         }
         if( $("#dto_"+i).val().search(",") >= 0 )
         {
            $("#dto_"+i).val( $("#dto_"+i).val().replace(",",".") );
         }
         if( $("#iva_"+i).val().search(",") >= 0 )
         {
            $("#iva_"+i).val( $("#iva_"+i).val().replace(",",".") );
         }
         if( $("#irpf_"+i).val().search(",") >= 0 )
         {
            $("#irpf_"+i).val( $("#irpf_"+i).val().replace(",",".") );
         }
         if( $("#recargo_"+i).val().search(",") >= 0 )
         {
            $("#recargo_"+i).val( $("#recargo_"+i).val().replace(",",".") );
         }
         
         l_uds = parseFloat( $("#cantidad_"+i).val() );
         l_pvp = parseFloat( $("#pvp_"+i).val() );
         l_dto = parseFloat( $("#dto_"+i).val() );
         l_neto = l_uds*l_pvp*(100-l_dto)/100;
         l_iva = parseFloat( $("#iva_"+i).val() );
         l_irpf = parseFloat( $("#irpf_"+i).val() );
         l_recargo = parseFloat( $("#recargo_"+i).val() );
         
         $("#neto_"+i).val( l_neto );
         if(numlineas == 1)
         {
            $("#total_"+i).val( fs_round(l_neto, fs_nf0) + fs_round(l_neto*(l_iva-l_irpf+l_recargo)/100, fs_nf0) );
         }
         else
         {
            $("#total_"+i).val( number_format(l_neto + (l_neto*(l_iva-l_irpf+l_recargo)/100), fs_nf0, '.', '') );
         }
         
         neto += l_neto;
         total_iva += l_neto * l_iva/100;
         total_irpf += l_neto * l_irpf/100;
         total_recargo += l_neto * l_recargo/100;
         
         /// adaptamos el alto del textarea al texto
         var txt = $("textarea[name='desc_"+i+"']").val();
         txt = txt.split(/\r*\n/);
         if(txt.length > 1)
         {
            $("textarea[name='desc_"+i+"']").prop('rows', txt.length);
         }
      }
   }
   
   neto = fs_round(neto, fs_nf0);
   total_iva = fs_round(total_iva, fs_nf0);
   total_irpf = fs_round(total_irpf, fs_nf0);
   total_recargo = fs_round(total_recargo, fs_nf0);
   $("#aneto").html( show_numero(neto) );
   $("#aiva").html( show_numero(total_iva) );
   $("#are").html( show_numero(total_recargo) );
   $("#airpf").html( show_numero(total_irpf) );
   $("#atotal").val( fs_round(neto + total_iva - total_irpf + total_recargo, fs_nf0) );
   
   if(total_recargo == 0 && !tiene_recargo)
   {
      $(".recargo").hide();
   }
   else
   {
      $(".recargo").show();
   }
   
   if(total_irpf == 0 && irpf == 0)
   {
      $(".irpf").hide();
   }
   else
   {
      $(".irpf").show();
   }
}

function ajustar_neto(i)
{
   var l_uds = 0;
   var l_pvp = 0;
   var l_dto = 0;
   var l_neto = 0;
   
   if($("#linea_"+i).length > 0)
   {
      /// cambiamos coma por punto
      if( $("#neto_"+i).val().search(",") >= 0 )
      {
         $("#neto_"+i).val( $("#neto_"+i).val().replace(",",".") );
      }
      
      l_uds = parseFloat( $("#cantidad_"+i).val() );
      l_pvp = parseFloat( $("#pvp_"+i).val() );
      l_dto = parseFloat( $("#dto_"+i).val() );
      l_neto = parseFloat( $("#neto_"+i).val() );
      if( isNaN(l_neto) )
      {
         l_neto = 0;
      }
      else if(l_neto < 0)
      {
         l_neto = Math.abs(l_neto);
      }
      
      if( l_neto <= l_pvp*l_uds )
      {
         l_dto = 100 - 100*l_neto/(l_pvp*l_uds);
         if( isNaN(l_dto) )
         {
            l_dto = 0;
         }
         
         l_dto = fs_round(l_dto, 2);
      }
      else
      {
         l_dto = 0;
         l_pvp = 100*l_neto/(l_uds*(100-l_dto));
         if( isNaN(l_pvp) )
         {
            l_pvp = 0;
         }
         
         l_pvp = fs_round(l_pvp, 4);
      }
      
      $("#pvp_"+i).val(l_pvp);
      $("#dto_"+i).val(l_dto);
   }
   
   recalcular();
}

function ajustar_total(i)
{
   var l_uds = 0;
   var l_pvp = 0;
   var l_dto = 0;
   var l_iva = 0;
   var l_irpf = 0;
   var l_recargo = 0;
   var l_neto = 0;
   var l_total = 0;
   
   if($("#linea_"+i).length > 0)
   {
      /// cambiamos coma por punto
      if( $("#total_"+i).val().search(",") >= 0 )
      {
         $("#total_"+i).val( $("#total_"+i).val().replace(",",".") );
      }
      
      l_uds = parseFloat( $("#cantidad_"+i).val() );
      l_pvp = parseFloat( $("#pvp_"+i).val() );
      l_dto = parseFloat( $("#dto_"+i).val() );
      l_iva = parseFloat( $("#iva_"+i).val() );
      l_recargo = parseFloat( $("#recargo_"+i).val() );
      l_irpf = parseFloat( $("#irpf_"+i).val() );
      
      l_total = parseFloat( $("#total_"+i).val() );
      if( isNaN(l_total) )
      {
         l_total = 0;
      }
      else if(l_total < 0)
      {
         l_total = Math.abs(l_total);
      }
      
      if( l_total <= l_pvp*l_uds + (l_pvp*l_uds*(l_iva-l_irpf+l_recargo)/100) )
      {
         l_neto = 100*l_total/(100+l_iva-l_irpf+l_recargo);
         l_dto = 100 - 100*l_neto/(l_pvp*l_uds);
         if( isNaN(l_dto) )
         {
            l_dto = 0;
         }
         
         l_dto = fs_round(l_dto, 2);
      }
      else
      {
         l_dto = 0;
         l_neto = 100*l_total/(100+l_iva-l_irpf+l_recargo);
         l_pvp = fs_round(l_neto/l_uds, 4);
      }
      
      $("#pvp_"+i).val(l_pvp);
      $("#dto_"+i).val(l_dto);
   }
   
   recalcular();
}

function ajustar_iva(num)
{
   if($("#linea_"+num).length > 0)
   {
      if(proveedor.regimeniva == 'Exento')
      {
         $("#iva_"+num).val(0);
         $("#recargo_"+num).val(0);
         
         alert('El proveedor tiene regimen de IVA: '+proveedor.regimeniva);
      }
      else if(siniva && $("#iva_"+num).val() != 0)
      {
         $("#iva_"+num).val(0);
         $("#recargo_"+num).val(0);
         
         alert('La serie selecciona es sin IVA.');
      }
      else if(tiene_recargo)
      {
         for(var i=0; i<all_impuestos.length; i++)
         {
            if($("#iva_"+num).val() == all_impuestos[i].iva)
            {
               $("#recargo_"+num).val(all_impuestos[i].recargo);
            }
         }
      }
   }
   
   recalcular();
}

function aux_all_impuestos(num,codimpuesto)
{
   var iva = 0;
   var recargo = 0;
   if(proveedor.regimeniva != 'Exento' && !siniva)
   {
      for(var i=0; i<all_impuestos.length; i++)
      {
         if(all_impuestos[i].codimpuesto == codimpuesto)
         {
            iva = all_impuestos[i].iva;
            if(tiene_recargo)
            {
               recargo = all_impuestos[i].recargo;
            }
            break;
         }
      }
   }
   
   var html = "<td><select id=\"iva_"+num+"\" class=\"form-control\" name=\"iva_"+num+"\" onchange=\"ajustar_iva('"+num+"')\">";
   for(var i=0; i<all_impuestos.length; i++)
   {
      if(iva == all_impuestos[i].iva)
      {
         html += "<option value=\""+all_impuestos[i].iva+"\" selected=\"\">"+all_impuestos[i].descripcion+"</option>";
      }
      else
         html += "<option value=\""+all_impuestos[i].iva+"\">"+all_impuestos[i].descripcion+"</option>";
   }
   html += "</select></td>";
   
   html += "<td class=\"recargo\"><input type=\"text\" class=\"form-control text-right\" id=\"recargo_"+num+"\" name=\"recargo_"+num+
         "\" value=\""+recargo+"\" onclick=\"this.select()\" onkeyup=\"recalcular()\" autocomplete=\"off\"/></td>";
   
   html += "<td class=\"irpf\"><input type=\"text\" class=\"form-control text-right\" id=\"irpf_"+num+"\" name=\"irpf_"+num+
         "\" value=\""+irpf+"\" onclick=\"this.select()\" onkeyup=\"recalcular()\" autocomplete=\"off\"/></td>";
   
   return html;
}

function add_articulo(ref,desc,pvp,dto,codimpuesto)
{
   desc = Base64.decode(desc);
   $("#lineas_albaran").append("<tr id=\"linea_"+numlineas+"\">\n\
      <td><input type=\"hidden\" name=\"idlinea_"+numlineas+"\" value=\"-1\"/>\n\
         <input type=\"hidden\" name=\"referencia_"+numlineas+"\" value=\""+ref+"\"/>\n\
         <div class=\"form-control\"><small><a target=\"_blank\" href=\"index.php?page=ventas_articulo&ref="+ref+"\">"+ref+"</a></small></div></td>\n\
      <td><textarea class=\"form-control\" id=\"desc_"+numlineas+"\" name=\"desc_"+numlineas+"\" rows=\"1\">"+desc+"</textarea></td>\n\
      <td><input type=\""+input_number+"\" step=\"any\" id=\"cantidad_"+numlineas+"\" class=\"form-control text-right\" name=\"cantidad_"+numlineas+
         "\" onchange=\"recalcular()\" onkeyup=\"recalcular()\" autocomplete=\"off\" value=\"1\"/></td>\n\
      <td><button class=\"btn btn-sm btn-danger\" type=\"button\" onclick=\"$('#linea_"+numlineas+"').remove();recalcular();\">\n\
         <span class=\"glyphicon glyphicon-trash\"></span></button></td>\n\
      <td><input type=\"text\" class=\"form-control text-right\" id=\"pvp_"+numlineas+"\" name=\"pvp_"+numlineas+"\" value=\""+pvp+
         "\" onkeyup=\"recalcular()\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      <td><input type=\"text\" id=\"dto_"+numlineas+"\" name=\"dto_"+numlineas+"\" value=\""+dto+
         "\" class=\"form-control text-right\" onkeyup=\"recalcular()\" onchange=\"recalcular()\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      <td><input type=\"text\" class=\"form-control text-right\" id=\"neto_"+numlineas+"\" name=\"neto_"+numlineas+
         "\" onchange=\"ajustar_neto("+numlineas+")\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      "+aux_all_impuestos(numlineas,codimpuesto)+"\n\
      <td class=\"warning\" title=\"Cálculo aproximado del total de la linea\">\n\
         <input type=\"text\" class=\"form-control text-right\" id=\"total_"+numlineas+"\" name=\"total_"+numlineas+
         "\" onchange=\"ajustar_total("+numlineas+")\" onclick=\"this.select()\" autocomplete=\"off\"/></td></tr>");
   numlineas += 1;
   $("#numlineas").val(numlineas);
   recalcular();
   
   $("#modal_articulos").modal('hide');
   
   $("#desc_"+(numlineas-1)).select();
   return false;
}

function add_articulo_atributos(ref,desc,pvp,dto,codimpuesto)
{
   $.ajax({
      type: 'POST',
      url: nueva_compra_url,
      dataType: 'html',
      data: "referencia4combi="+ref+"&desc="+desc+"&pvp="+pvp+"&dto="+dto+"&codimpuesto="+codimpuesto,
      success: function(datos) {
         $("#nav_articulos").hide();
         $("#search_results").html(datos);
      }
   });
}

function add_linea_libre()
{
   codimpuesto = false;
   for(var i=0; i<all_impuestos.length; i++)
   {
      codimpuesto = all_impuestos[i].codimpuesto;
      break;
   }
   
   $("#lineas_albaran").append("<tr id=\"linea_"+numlineas+"\">\n\
      <td><input type=\"hidden\" name=\"idlinea_"+numlineas+"\" value=\"-1\"/>\n\
         <input type=\"hidden\" name=\"referencia_"+numlineas+"\"/>\n\
         <div class=\"form-control\"></div></td>\n\
      <td><textarea class=\"form-control\" id=\"desc_"+numlineas+"\" name=\"desc_"+numlineas+"\" rows=\"1\"></textarea></td>\n\
      <td><input type=\""+input_number+"\" step=\"any\" id=\"cantidad_"+numlineas+"\" class=\"form-control text-right\" name=\"cantidad_"+numlineas+
         "\" onchange=\"recalcular()\" onkeyup=\"recalcular()\" autocomplete=\"off\" value=\"1\"/></td>\n\
      <td><button class=\"btn btn-sm btn-danger\" type=\"button\" onclick=\"$('#linea_"+numlineas+"').remove();recalcular();\">\n\
         <span class=\"glyphicon glyphicon-trash\"></span></button></td>\n\
      <td><input type=\"text\" class=\"form-control text-right\" id=\"pvp_"+numlineas+"\" name=\"pvp_"+numlineas+"\" value=\"0\"\n\
          onkeyup=\"recalcular()\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      <td><input type=\"text\" id=\"dto_"+numlineas+"\" name=\"dto_"+numlineas+"\" value=\"0\" class=\"form-control text-right\"\n\
          onkeyup=\"recalcular()\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      <td><input type=\"text\" class=\"form-control text-right\" id=\"neto_"+numlineas+"\" name=\"neto_"+numlineas+
         "\" onchange=\"ajustar_neto("+numlineas+")\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      "+aux_all_impuestos(numlineas,codimpuesto)+"\n\
      <td class=\"warning\" title=\"Cálculo aproximado del total de la linea\">\n\
         <input type=\"text\" class=\"form-control text-right\" id=\"total_"+numlineas+"\" name=\"total_"+numlineas+
         "\" onchange=\"ajustar_total("+numlineas+")\" onclick=\"this.select()\" autocomplete=\"off\"/></td></tr>");
   numlineas += 1;
   $("#numlineas").val(numlineas);
   recalcular();
   
   $("#desc_"+(numlineas-1)).select();
   return false;
}

function get_precios(ref)
{
   if(nueva_compra_url !== '')
   {
      $.ajax({
         type: 'POST',
         url: nueva_compra_url,
         dataType: 'html',
         data: "referencia4precios="+ref+"&codproveedor="+proveedor.codproveedor,
         success: function(datos) {
            $("#nav_articulos").hide();
            $("#search_results").html(datos);
         }
      });
   }
}

function new_articulo()
{
   if(nueva_compra_url !== '')
   {
      $.ajax({
         type: 'POST',
         url: nueva_compra_url+'&new_articulo=TRUE',
         dataType: 'json',
         data: $("form[name=f_nuevo_articulo]").serialize(),
         success: function(datos) {
            if(typeof datos[0] == 'undefined')
            {
               if(document.f_nuevo_articulo.referencia.value == '')
               {
                  alert('Debes escribir una referencia.');
               }
               else
               {
                  alert('Se ha producido un error al crear el artículo.');
               }
            }
            else
            {
               document.f_buscar_articulos.query.value = document.f_nuevo_articulo.referencia.value;
               $("#nav_articulos li").each(function() {
                  $(this).removeClass("active");
               });
               $("#li_mis_articulos").addClass('active');
               $("#search_results").show();
               $("#kiwimaru_results").hide();
               $("#nuevo_articulo").hide();
               
               if(precio_compra == 'coste')
               {
                  add_articulo(datos[0].referencia, Base64.encode(datos[0].descripcion), datos[0].coste, 0, datos[0].codimpuesto);
               }
               else
               {
                  add_articulo(datos[0].referencia, Base64.encode(datos[0].descripcion), datos[0].pvp, 0, datos[0].codimpuesto);
               }
            }
         }
      });
   }
}

function buscar_articulos()
{
   document.f_nuevo_articulo.referencia.value = document.f_buscar_articulos.query.value;
   document.f_nuevo_articulo.refproveedor.value = document.f_buscar_articulos.query.value;
   
   if(document.f_buscar_articulos.query.value == '')
   {
      $("#nav_articulos").hide();
      $("#search_results").html('');
      $("#kiwimaru_results").html('');
      $("#nuevo_articulo").hide();
      
      fin_busqueda1 = true;
      fin_busqueda2 = true;
   }
   else
   {
      $("#nav_articulos").show();
      
      if(nueva_compra_url !== '')
      {
         fin_busqueda1 = false;
         $.getJSON(nueva_compra_url, $("form[name=f_buscar_articulos]").serialize(), function(json) {
            var items = [];
            var insertar = false;
            $.each(json, function(key, val) {
               var stock = val.stockalm;
               if(val.stockalm != val.stockfis)
               {
                  stock += ' <span title="stock general">('+val.stockfis+')</span>';
               }
               
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
               
               var precio = val.coste;
               if(precio_compra == 'pvp')
               {
                  precio = val.pvp;
               }
               
               var tr_aux = '<tr>';
               if(val.bloqueado)
               {
                  tr_aux = "<tr class=\"danger\">";
               }
               else if(val.stockfis < val.stockmin)
               {
                  tr_aux = "<tr class=\"warning\">";
               }
               else if(val.stockfis > val.stockmax)
               {
                  tr_aux = "<tr class=\"success\">";
               }
               
               if(val.secompra)
               {
                  var funcion = "add_articulo('"+val.referencia+"','"+descripcion+"','"+precio+"','"
                          +val.dtopor+"','"+val.codimpuesto+"','"+val.cantidad+"')";
                  var funcion1 = "add_articulo('"+val.referencia+"','"+descripcion+"','"+val.coste+"','"
                          +val.dtopor+"','"+val.codimpuesto+"','"+val.cantidad+"')";
                  var funcion2 = "add_articulo('"+val.referencia+"','"+descripcion+"','"+val.pvp+"','"
                          +val.dtopor+"','"+val.codimpuesto+"','"+val.cantidad+"')";
                  
                  if(val.tipo)
                  {
                     funcion = "add_articulo_"+val.tipo+"('"+val.referencia+"','"+descripcion+"','"
                             +precio+"','"+val.dtopor+"','"+val.codimpuesto+"','"+val.cantidad+"')";
                     funcion1 = "add_articulo_"+val.tipo+"('"+val.referencia+"','"+descripcion+"','"
                             +val.coste+"','"+val.dtopor+"','"+val.codimpuesto+"','"+val.cantidad+"')";
                     funcion2 = "add_articulo_"+val.tipo+"('"+val.referencia+"','"+descripcion+"','"
                             +val.pvp+"','"+val.dtopor+"','"+val.codimpuesto+"','"+val.cantidad+"')";
                  }
                  
                  items.push(tr_aux+"<td><a href=\"#\" onclick=\"get_precios('"+val.referencia+"')\" title=\"más detalles\">\n\
                     <span class=\"glyphicon glyphicon-eye-open\"></span></a>\n\
                     &nbsp; <a href=\"#\" onclick=\"return "+funcion+"\">"+val.referencia+'</a> '+descripcion_visible+"</td>\n\
                     <td class=\"text-right\"><a href=\"#\" onclick=\"return "+funcion1+"\">"+show_precio(val.coste, val.coddivisa)+"</a></td>\n\
                     <td class=\"text-right\"><a href=\"#\" onclick=\"return "+funcion2+"\" title=\"actualizado el "
                          +val.factualizado+"\">"+show_precio(val.pvp, val.coddivisa)+"</a></td>\n\
                     <td class=\"text-right\">"+stock+"</td></tr>");
               }
               
               if(val.query == document.f_buscar_articulos.query.value)
               {
                  insertar = true;
                  fin_busqueda1 = true;
               }
            });
            
            if(items.length == 0 && !fin_busqueda1)
            {
               items.push("<tr><td colspan=\"4\" class=\"warning\">Sin resultados. Usa la pestaña\n\
                              <b>Nuevo</b> para crear uno.</td></tr>");
               document.f_nuevo_articulo.referencia.value = document.f_buscar_articulos.query.value;
               insertar = true;
            }
            
            if(insertar)
            {
               $("#search_results").html("<div class=\"table-responsive\"><table class=\"table table-hover\"><thead><tr>\n\
                  <th class=\"text-left\">Referencia + descripción</th>\n\
                  <th class=\"text-right\" width=\"80\">Compra</th>\n\
                  <th class=\"text-right\" width=\"80\">Venta</th>\n\
                  <th class=\"text-right\" width=\"80\">Stock</th>\n\
                  </tr></thead>"+items.join('')+"</table></div>\n\
                  <p class='help-block'>&nbsp; <span class='glyphicon glyphicon-info-sign'></span>\n\
                  Los precios de compra son los de este proveedor, o bien el precio de coste del artículo.\n\
                  Puedes modificar esta configuración desde Admin &gt; Almacenes.</p>");
            }
         });
      }
      
      if(document.f_buscar_articulos.coddivisa.value != 'EUR')
      {
         fin_busqueda2 = false;
         $("#kiwimaru_results").html("<p class=\"help-block\" style=\"padding: 5px;\"><b>Kiwimaru</b>\n\
            no dispone de resultados para la divisa seleccionada.</p>");
      }
      else if(kiwimaru_url !== '')
      {
         fin_busqueda2 = false;
         $.getJSON(kiwimaru_url, $("form[name=f_buscar_articulos]").serialize(), function(json) {
            var items = [];
            var insertar = false;
            $.each(json, function(key, val) {
               items.push( "<tr><td>"+val.sector+" / <a href=\""+val.link+"\" target=\"_blank\">"
                       +val.tienda+"</a> / "+val.familia+"</td>\n\
                  <td><a href=\""+val.link+"\" target=\"_blank\"><span class=\"glyphicon glyphicon-eye-open\"></span></a>\n\
                  <a href=\"#\" onclick=\"kiwi_import('"+val.referencia+"','"+val.descripcion+"','"+val.precio+"')\">"
                       +val.referencia+'</a> '+val.descripcion+"</td>\n\
                  <td class=\"text-right\"><a href=\"#\" onclick=\"kiwi_import('"
                       +val.referencia+"','"+val.descripcion+"','"+val.precio+"')\">"+show_precio(val.precio)+"</a></td></tr>" );
               
               if(val.query == document.f_buscar_articulos.query.value)
               {
                  insertar = true;
                  fin_busqueda2 = true;
               }
            });
            
            if(items.length == 0 && !fin_busqueda2)
            {
               items.push("<tr><td colspan=\"3\" class=\"warning\">Sin resultados.</td></tr>");
               insertar = true;
            }
            
            if(insertar)
            {
               $("#kiwimaru_results").html("<p class=\"help-block\" style=\"padding: 5px;\">Estos son\n\
                  los resultados de <b>kiwimaru</b>, el potente buscador de tiendas online integrado en\n\
                  FacturaScripts, para que puedas buscar nuevos proveedores o simplemente comparar precios.\n\
                  Si deseas añadir tus artículos a este buscador y ganar nuevos clientes fácilmente,\n\
                  <a href=\"https://www.facturascripts.com/feedback?feedback_plugin=46\" target=\"_blank\">\n\
                  contacta con nosotros</a>.</p>\n\
                  <div class=\"table-responsive\"><table class=\"table table-hover\"><thead><tr>\n\
                  <th class=\"text-left\">Sector / Tienda / Familia</th><th class=\"text-left\">Referencia + descripción</th>\n\
                  <th class=\"text-right\">Precio+IVA</th></tr></thead>"+items.join('')+"</table></div>");
            }
         });
      }
   }
}

function kiwi_import(ref,desc,pvp)
{
   $("#nav_articulos li").each(function() {
      $(this).removeClass("active");
   });
   $("#li_nuevo_articulo").addClass('active');
   $("#search_results").hide();
   $("#kiwimaru_results").hide();
   $("#nuevo_articulo").show();
   document.f_nuevo_articulo.referencia.value = ref;
   document.f_nuevo_articulo.refproveedor.value = ref;
   document.f_nuevo_articulo.descripcion.value = desc;
   document.f_nuevo_articulo.coste.value = pvp;
   document.f_nuevo_articulo.pvp.value = pvp;
   document.f_nuevo_articulo.referencia.select();
}

$(document).ready(function() {
   $("#i_new_line").click(function() {
      $("#i_new_line").val("");
      $("#nav_articulos li").each(function() {
         $(this).removeClass("active");
      });
      $("#li_mis_articulos").addClass('active');
      $("#search_results").show();
      $("#kiwimaru_results").html('');
      $("#kiwimaru_results").hide();
      $("#nuevo_articulo").hide();
      $("#modal_articulos").modal('show');
      document.f_buscar_articulos.query.select();
   });
   
   $("#i_new_line").keyup(function() {
      document.f_buscar_articulos.query.value = $("#i_new_line").val();
      $("#i_new_line").val('');
      $("#nav_articulos li").each(function() {
         $(this).removeClass("active");
      });
      $("#li_mis_articulos").addClass('active');
      $("#search_results").show();
      $("#kiwimaru_results").html('');
      $("#kiwimaru_results").hide();
      $("#nuevo_articulo").hide();
      $("#modal_articulos").modal('show');
      document.f_buscar_articulos.query.select();
      buscar_articulos();
   });
   
   $("#f_buscar_articulos").keyup(function() {
      buscar_articulos();
   });
   
   $("#f_buscar_articulos").submit(function(event) {
      event.preventDefault();
      buscar_articulos();
   });
   
   $("#b_mis_articulos").click(function(event) {
      event.preventDefault();
      $("#nav_articulos li").each(function() {
         $(this).removeClass("active");
      });
      $("#li_mis_articulos").addClass('active');
      $("#kiwimaru_results").hide();
      $("#nuevo_articulo").hide();
      $("#search_results").show();
      document.f_buscar_articulos.query.focus();
   });
   
   $("#b_kiwimaru").click(function(event) {
      event.preventDefault();
      $("#nav_articulos li").each(function() {
         $(this).removeClass("active");
      });
      $("#li_kiwimaru").addClass('active');
      $("#nuevo_articulo").hide();
      $("#search_results").hide();
      $("#kiwimaru_results").show();
      document.f_buscar_articulos.query.focus();
   });
   
   $("#b_nuevo_articulo").click(function(event) {
      event.preventDefault();
      $("#nav_articulos li").each(function() {
         $(this).removeClass("active");
      });
      $("#li_nuevo_articulo").addClass('active');
      $("#search_results").hide();
      $("#kiwimaru_results").hide();
      $("#nuevo_articulo").show();
      document.f_nuevo_articulo.referencia.select();
   });
});