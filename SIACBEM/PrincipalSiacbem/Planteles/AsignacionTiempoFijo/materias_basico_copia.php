<?php
	if (isset($_POST['id_estructura']) AND !empty($_POST['id_estructura']) AND isset($_POST['id_subprograma']) AND !empty($_POST['id_subprograma']) AND isset($_POST['id_componente']) AND !empty($_POST['id_componente']) AND isset($_POST['semestre']) AND !empty($_POST['semestre']) ) {	
		$id_estructura = intval($_POST["id_estructura"]);
		$id_subprograma = intval($_POST["id_subprograma"]);
		$id_componente = intval($_POST["id_componente"]);
		$periodo = $_POST["periodo"];
		$semestre = intval($_POST["semestre"]);
		$id_usuario = intval($_POST["id_usuario"]);
		$a_materias = array();
		$a_grupos = array(); 
		$asignados_tf = array(); 

		require_once('../../ipReportes.php');
		$instancia = new IpReporte; 
		$ipCF = $instancia->getIp();

		$url_reporte = "${ipCF}/siacbem/ReportesPDF/ConvocatoriaTiempoFijo/propuesta_nombramiento.cfm?";

		// Agrupa cuando es un grupo y la misma materia pero tiene dos o mas asignaciones (2 o mas plazas)
		function agrupa_elegidos( $data_elegidos ){

			$a_elegidos = array();

			// buscar 
			foreach ($data_elegidos as $key => $item) {
				
				$id_gpo_estruc_base = $item["id_grupo_estructura_base"];	

				foreach ($a_elegidos as $key1 => $item1) {
					
					$existe = ($item['id_grupo_estructura_base'] == $item1['id_grupo_estructura_base'] AND $item['id_detalle_materia'] == $item1['id_detalle_materia']  ) ? true : false;
					$indice = $key1;

				}

				if (!$existe)
					array_push($a_elegidos, $item);
				else{

					$a_elegidos[$indice]['sustituye'] = $item['sustituye'];
					$a_elegidos[$indice]['id_tramite_licencia_asignacion'] = $item['id_tramite_licencia_asignacion'];
					$a_elegidos[$indice]['id_tipo_movimiento_personal'] = $item['id_tipo_movimiento_personal'];

					if ($a_elegidos[$indice]['licencia'] == 'f')
						$a_elegidos[$indice]['licencia'] = $item['licencia'];
					

					$a_elegidos[$indice]['horas_grupo_base']+= intval($item['horas_grupo_base']);

				
				}
				

			}

			return $a_elegidos;
		}


		function pintar_asignado( $gpos, $id_subprograma, $id_estructura, $url_reporte, $params_asignacion ){

			$params_reporte = array(
				"id_emp" => $gpos['asignado']['id_empleado'],
				"id_materia" => $gpos['asignado']['id_detalle_materia'],
				"id_subp" => $id_subprograma,
				"id_estruc" => $id_estructura,
				"tabla" => 'asignacion_tiempo_fijo_basico',
			);

			$rutaPropuesta = $url_reporte.http_build_query($params_reporte);
			$id_asignado = $gpos['asignado']['id_asignacion_tiempo_fijo_basico'];

			?>
			<table class="table-white bordered-0">
				<tbody>
					<tr>
						<td>
							<?php
							if (!empty($gpos['sustituye'])) {
								echo "<p class='text-edit margin-bottom'><i>(Sustituye a ${gpos['sustituye']})</i></p>";
							}
							?> 
							<strong><?php echo $gpos['asignado']['nombre_completo'];?></strong> 
							
						</td>
					</tr>
					<tr>
						<td> <?php echo $gpos['asignado']['categoria'];?> </td>
					</tr>
					<tr>
						<td> Alta <?php echo $gpos['asignado']['codigo'];?> </td>
					</tr>
					<tr>
						<?php

						if (intval($gpos['asignado']['valida_perfil']) == 0) {
							$cumple_perfil = 'No cumple perfil';
							$color_perfil = 'text-danger';
						}else{
							$cumple_perfil = 'Cumple perfil';
							$color_perfil = 'text-success';
						}

						?>
						<td> <p class="<?php echo $color_perfil?>"><?php echo $cumple_perfil;?></p> </td>
					</tr>
					<tr>
						<td>
							<button class="btn-edit" onclick="PlantelesTF_PropuestaNombramiento('<?php echo $rutaPropuesta;?>')">Propuesta Nombramiento TF</button>
							
							<?php
							if ($gpos['asignado']['doc_acuerdo'] != 't') {
								?>
							<button type="button" class="btn-danger icon-borrar" sm onclick="PlantelesAsignarTiempoFijo_Eliminar(<?php echo $id_asignado;?>,'<?php echo htmlentities(json_encode($params_asignacion));?>')"></button>
							<?php
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
			<?php

		}

		function fase_tf( $data ){

			$etapa = 0;
			//$fecha_actual = strtotime(date("Y-m-d",time()));
			$fecha_actual = strtotime(date("2020-08-17",time()));

			foreach ($data as $key => $item) {
				
				$fecha_inicio = strtotime( $item['fecha_inicio'] );
				$fecha_fin = strtotime( $item['fecha_fin'] );


				if ( $fecha_actual >= $fecha_inicio AND $fecha_actual <= $fecha_fin )
					$etapa = $item['fase'];

			}

			return $etapa;
		}
		
		include("../../conexiones/seconecta.php");

		// saber en que etapa de tiempo fijo estamos
		$query_etapa_tf = "
			SELECT fecha_inicio, fecha_fin, fase
			FROM planteles_asignacion_calendario
			WHERE id_subprograma = ${id_subprograma}
			AND id_estructura_ocupacional = ${id_estructura}
			AND fase >= 2
			ORDER BY fase ASC
		";
		$res_etapa_tf = pg_query($query_etapa_tf);
		$data_etapa_tf = pg_fetch_all($res_etapa_tf);
		pg_free_result($res_etapa_tf);

		// saber grupos del semestre
		$query_grupos = "
			SELECT a.id_grupo_estructura_base,a.nombre_grupo,id_plan_grupo_activo1,id_plan_grupo_activo2,id_plan_grupo_activo3,gc.id_grupo_combinacion_plan
			FROM grupos_estructura_base a
			INNER JOIN horas_autorizadas b ON b.id_hora_autorizada = a.id_hora_autorizada
			INNER JOIN grupos c ON b.id_grupo = c.id_grupo
			INNER JOIN grupos_combinaciones_planes gc ON gc.id_grupo_combinacion_plan = c.id_grupo_combinacion_plan
			INNER JOIN periodos d ON c.id_periodo = d.id_periodo
			WHERE d.id_subprograma = $id_subprograma 
			AND	d.id_estructura_ocupacional = $id_estructura
			AND a.nombre_grupo like '$semestre%'
		";
		$res_grupos = pg_query($query_grupos);
		$a_grupos = pg_fetch_all($res_grupos);
		pg_free_result($res_grupos);


		// saber id plan estudios
		if($semestre == 1 OR $semestre == 2)
			$id_plan_estudio = $a_grupos[0]["id_plan_grupo_activo1"];
		else if($semestre == 3 OR $semestre == 4)
			$id_plan_estudio = $a_grupos[0]["id_plan_grupo_activo2"];
		else
			$id_plan_estudio = $a_grupos[0]["id_plan_grupo_activo3"];

		$id_grupo_combinacion_plan = $a_grupos[0]['id_grupo_combinacion_plan'];

		// saber las materias del semestre
		/*
			Esta condicion en la query me distingue si es Plantel/Extension o si es un CEM
			y me muestra Orientación educativa normal u Orientación educativa CEM

			AND (
				CASE WHEN f.nombre_subprograma ilike 'Plantel%' THEN
					b.materia not ilike '%ORIENTACIÓN EDUCATIVA CEM%'
				ELSE 
					(b.materia != 'ORIENTACIÓN EDUCATIVA I' AND b.materia != 'ORIENTACIÓN EDUCATIVA II' AND b.materia != 'ORIENTACIÓN EDUCATIVA III' AND b.materia != 'ORIENTACIÓN EDUCATIVA IV' AND b.materia != 'ORIENTACIÓN EDUCATIVA V')
				END
			)
		*/
		$query_materias = "
		    SELECT  
		    a.id_detalle_materia,a.hora_semana_mes,b.materia 
		    FROM detalle_materias a
		    INNER JOIN cat_materias b ON b.id_materia = a.id_materia
		    INNER JOIN plan_estudios c ON c.id_plan_estudio = a.id_plan_estudio 
			INNER JOIN grupos d ON d.id_grupo_combinacion_plan = $id_grupo_combinacion_plan
		    INNER JOIN periodos e ON e.id_periodo = d.id_periodo
		    INNER JOIN subprogramas f ON f.id_subprograma = e.id_subprograma
		    WHERE a.id_plan_estudio = $id_plan_estudio 
		    AND (a.id_componente = $id_componente OR a.id_componente = 6) 
		    AND a.semestre = $semestre
		    AND e.id_tipo_periodo = 1 
		    AND e.id_subprograma = $id_subprograma 
		    AND e.id_estructura_ocupacional = $id_estructura
		    AND b.fecha_fin is null
		    AND (
				CASE WHEN f.nombre_subprograma ilike 'Plantel%' THEN
					b.materia not ilike '%ORIENTACIÓN EDUCATIVA CEM%'
				ELSE 
					(b.materia != 'ORIENTACIÓN EDUCATIVA I' AND b.materia != 'ORIENTACIÓN EDUCATIVA II' AND b.materia != 'ORIENTACIÓN EDUCATIVA III' AND b.materia != 'ORIENTACIÓN EDUCATIVA IV' AND b.materia != 'ORIENTACIÓN EDUCATIVA V')
				END
			)
		    ORDER BY a.id_componente desc, a.id_detalle_materia	
		";
		$res_materias = pg_query($query_materias);
		$a_materias = pg_fetch_all($res_materias);
		pg_free_result($res_materias);


		// grupos-materias asignados en estructura base
		$query_elegidos ="
			SELECT 
			a.id_grupo_estructura_base,h.id_detalle_materia,h.hora_semana_mes,sum(horas_grupo_base) as horas_grupo_base 
			,COALESCE(pp.status_licencia,false) as licencia
			,COALESCE((
				SELECT tl.id_movimiento_interino
				FROM tramites_licencias_asignaciones tla
				INNER JOIN tramites_licencias tl ON tl.id_tramite_licencia = tla.id_tramite_licencia
				WHERE tla.id_asignacion = pp.id_profesores_profesor_asignado_base
			),4) as id_tipo_movimiento_personal
			,COALESCE((		
				SELECT horas_grupo_base
				FROM tramites_licencias_asignaciones tla
				INNER JOIN tramites_licencias tl ON tl.id_tramite_licencia = tla.id_tramite_licencia
				INNER JOIN tramites_licencias_plazas_docente tlpd ON tlpd.id_tramite_licencia = tl.id_tramite_licencia
				INNER JOIN profesores_profesor_asignado_base prof ON prof.id_profesores_profesor_asignado_base = tla.id_asignacion  
				WHERE tla.id_asignacion = pp.id_profesores_profesor_asignado_base

			),hora_semana_mes - horas_grupo_base) as horas_asignar
			,COALESCE((
				SELECT empl.paterno||' '||empl.materno||' '||empl.nombre
				FROM tramites_licencias_asignaciones tla
				INNER JOIN tramites_licencias tl ON tl.id_tramite_licencia = tla.id_tramite_licencia
				INNER JOIN tramites_licencias_plazas_docente tlpd ON tlpd.id_tramite_licencia = tl.id_tramite_licencia
				INNER JOIn plantilla_base_docente_rh pbdr ON pbdr.id_plantilla_base_docente_rh = tlpd.id_plantilla_base_docente_rh
				INNER JOIN empleados empl ON empl.id_empleado = pbdr.id_empleado
				WHERE tla.id_asignacion = pp.id_profesores_profesor_asignado_base
			),'') as sustituye
			,(
				SELECT id_tramite_licencia_asignacion
				FROM tramites_licencias_asignaciones tla
				WHERE tla.id_asignacion = pp.id_profesores_profesor_asignado_base
			) as id_tramite_licencia_asignacion
			FROM profesor_asignado_base a
			INNER JOIN profesores_profesor_asignado_base pp ON pp.id_profesor_asignado_base = a.id_profesor_asignado_base 
			INNER JOIN plantilla_base_docente_rh pbr ON pbr.id_plantilla_base_docente_rh = pp.id_plantilla_base_docente_rh
			INNER JOIN cat_categorias_padre ccp ON ccp.id_cat_categoria_padre = pbr.id_cat_categoria_padre
			INNER JOIN empleados b ON b.id_empleado = pbr.id_empleado
			INNER JOIN grupos_estructura_base c ON c.id_grupo_estructura_base = a.id_grupo_estructura_base
			INNER JOIN horas_autorizadas d ON d.id_hora_autorizada = c.id_hora_autorizada
			INNER JOIN grupos e ON e.id_grupo = d.id_grupo
			INNER JOIN periodos f ON f.id_periodo = e.id_periodo 
			INNER JOIN grupos_combinaciones_planes g ON g.id_grupo_combinacion_plan = e.id_grupo_combinacion_plan
			INNER JOIN detalle_materias h ON h.id_detalle_materia = a.id_detalle_materia 
			INNER JOIN cat_materias i ON i.id_materia = h.id_materia
			INNER JOIN cat_tipo_movimiento_personal j ON j.id_tipo_movimiento_personal = pp.id_tipo_movimiento_personal
			WHERE f.id_subprograma = $id_subprograma 
			and f.id_estructura_ocupacional = $id_estructura 
			and i.fecha_fin is null
			and  h.semestre = $semestre
			and e.id_grupo_combinacion_plan = $id_grupo_combinacion_plan
			GROUP BY a.id_grupo_estructura_base, h.id_detalle_materia, pp.status_licencia, pp.id_profesores_profesor_asignado_base
			ORDER BY a.id_grupo_estructura_base, h.id_detalle_materia,a.id_grupo_estructura_base, licencia ASC
		";
		$res_elegidos = pg_query($query_elegidos);
		$data_elegidos = pg_fetch_all($res_elegidos);
		pg_free_result($res_elegidos);
		$data_elegidos = agrupa_elegidos( $data_elegidos );

		/* 
			ASIGNACION TIEMPO FIJO
			grupos - materias elegidos
		*/
		$query_tiempo_fijo = "
			SELECT 
			a.id_asignacion_tiempo_fijo_basico
			,b.id_empleado,b.nombre,b.paterno,b.materno
			,b.nombre||' '||b.paterno||' '||b.materno as nombre_completo
			,c.id_detalle_materia,a.id_grupo_estructura_base, d.materia, e.categoria_padre
			,a.horas_grupo, e.categoria_padre||'/-/'||a.horas_grupo as categoria
			, COALESCE( a.doc_acuerdo, false ) as doc_acuerdo
			,ctmp.codigo
			,planteles_valida_perfil_1(b.id_empleado, c.id_detalle_materia) as valida_perfil
			FROM asignacion_tiempo_fijo_basico a
			INNER JOIN empleados b ON a.id_empleado = b.id_empleado
			INNER JOIN detalle_materias c ON c.id_detalle_materia = a.id_detalle_materia
			INNER JOIN cat_materias d ON d.id_materia = c.id_materia
			INNER JOIN cat_categorias_padre e ON e.id_cat_categoria_padre = a.id_cat_categoria_padre
			INNER JOIN cat_tipo_movimiento_personal ctmp ON ctmp.id_tipo_movimiento_personal = a.id_tipo_movimiento_personal
			WHERE a.id_estructura_ocupacional = $id_estructura
			AND a.id_subprograma = $id_subprograma
			AND c.semestre = $semestre
		";
		// echo "<pre>$query_tiempo_fijo</pre>";
		$res_tiempo_fijo = pg_query($query_tiempo_fijo);
		$data_asignados_tf = pg_fetch_all($res_tiempo_fijo);
		pg_free_result($res_tiempo_fijo);
		pg_close($link);


		// Agrego grupos a cada materia
		foreach ($a_materias as $key => $item) {

			$a_materias[$key]['grupos'] = $a_grupos;

			foreach ($a_materias[$key]['grupos'] as $key1 => $gpos) {
				$a_materias[$key]['grupos'][$key1]['horas_asignar'] = $item['hora_semana_mes'];
				$a_materias[$key]['grupos'][$key1]['id_tipo_movimiento_personal'] = 4;
				$a_materias[$key]['grupos'][$key1]['id_tramite_licencia_asignacion'] = 0;
				$a_materias[$key]['grupos'][$key1]['asignado'] = array();
			}
		
		}

		foreach ($a_materias as $key => $item) {
			// Reviso si el grupo-materia esta asignado
			foreach ($data_elegidos as $key1 => $asignado) {
				
				// Coincide la materia	
				if ($item['id_detalle_materia'] == $asignado['id_detalle_materia'] ) {
					
					// Coincide el grupo
					foreach ($a_materias[$key]['grupos'] as $key2 => $gpos) {
						

						if ($gpos['id_grupo_estructura_base'] == $asignado['id_grupo_estructura_base']) {

							$a_materias[$key]['grupos'][$key2]['horas_asignar'] = 0;
							$a_materias[$key]['grupos'][$key2]['sustituye'] = $asignado['sustituye'];
							$a_materias[$key]['grupos'][$key2]['id_tipo_movimiento_personal'] = $asignado['id_tipo_movimiento_personal'];
							$a_materias[$key]['grupos'][$key2]['id_tramite_licencia_asignacion'] = $asignado['id_tramite_licencia_asignacion'];
							
							if ( (intval($asignado['hora_semana_mes']) - intval($asignado['horas_grupo_base'])) <= 0 AND $asignado['licencia'] == 'f' ) {
								unset( $a_materias[$key]['grupos'][$key2] );
								$a_materias[$key]['grupos'] = array_values($a_materias[$key]['grupos']);
							}else{

								if ($asignado['licencia'] == 't')
									$a_materias[$key]['grupos'][$key2]['horas_asignar']+= $asignado['horas_asignar'];
								else
									$a_materias[$key]['grupos'][$key2]['horas_asignar']+= $asignado['horas_grupo_base'];
								
							}

						}

					}

				}

			}
		}


		// Agrego grupos asignados en TF
		foreach ($data_asignados_tf as $key => $row) {
			
			$idx1 = trim(array_search($row["id_detalle_materia"], array_column($a_materias, 'id_detalle_materia')));
			$idx2 = trim(array_search($row["id_grupo_estructura_base"], array_column($a_materias[$idx1]['grupos'], 'id_grupo_estructura_base')));
			
			$a_materias[$idx1]['grupos'][$idx2]['asignado'] = $row;
		}
		


		// borrar materias que no tengan vacantes
		foreach ($a_materias as $key => $item){
			
			if( count($item['grupos']) == 0 )
				unset($a_materias[$key]);
		
		}
		$a_materias = array_values($a_materias);

		// echo "<pre>";
		// print_r($a_materias);
		// echo "</pre>";
		if (count($a_materias) > 0) {

		foreach ($a_materias as $key => $item) {

			$id_detalle_materia = $item['id_detalle_materia'];
			$materia = $item['materia'];
			
		?>
		<table class="table-def bordered margin-bottom full">
			<thead>
				<tr>
					<th></th>
					<?php
					foreach ($item['grupos'] as $key1 => $gpos) {
					?>
						<th> <?php echo $gpos['nombre_grupo']." (".$gpos['horas_asignar']." hsm)";?> </th>
					<?php	
					}
					?>	
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><strong> <?php echo $item['materia'];?> </strong> </td>
					<?php
					foreach ($item['grupos'] as $key2 => $gpos) {

					// $valida_perfil = $gpos[]['valida_perfil'];	
					$id_grupo = $gpos['id_grupo_estructura_base'];
					$indice_profesor = $id_detalle_materia."_".$id_grupo;

					// echo "<br>doc: ".$item_gpo['asignacion']['doc_acuerdo'];
					$params_asignacion = array(
						"id_detalle_materia" => $id_detalle_materia,
						"materia" => $materia,
						"id_grupo" => $id_grupo,
						"id_estructura" => $id_estructura,
						"id_subprograma" => $id_subprograma,
						"id_componente" => $id_componente,
						"periodo" => $periodo,
						"hora_semana_mes" => $gpos['horas_asignar'],
						"id_usuario" => $id_usuario,
						"id_tipo_movimiento_personal" => $gpos['id_tipo_movimiento_personal'],
						"id_tramite_licencia_asignacion" => $gpos['id_tramite_licencia_asignacion'],
						"etapa" => fase_tf( $data_etapa_tf )
					);	

					?>
						<td id="td_<?php echo $indice_profesor;?>"> 
							<?php	

							if (count($gpos['asignado']) > 0) {
								
								pintar_asignado( $gpos, $id_subprograma, $id_estructura, $url_reporte, $params_asignacion );

							}else{
								// busqueda empleado especial para tiempo fijo
								$input_emp_tf = 'emp_tf_'.$indice_profesor;
								$input_hide_emp_tf = 'id_emp_tf_'.$indice_profesor;
								$ph_emp_tf = 'Busca por Nombre | Paterno | Materno | Filiacion, etc';
								$nom_fun_emp_tf = 'PlantelesAsignarTiempoFijo_CachaItemSeleccionado';
								$div_suggest_emp_tf = 'div_s_emp_tf_'.$indice_profesor;
								include('../../Programacion/FuncionesEmpaquetadas/BuscaEmpleadoTF/buscador_empleado.php');
									?>
								<div id="div_confirma_profesor_<?php echo $indice_profesor;?>" class="margin-top padding-top"></div>
							<?php	
							}
							?>
						</td>
					<?php	
					}
					?>
				</tr>
			</tbody>
		</table>
		<?php	
		}
					
		}else{
			echo "<p class='margin-top padding bg-warning'>No hay grupos-materias vacantes.</p>";	
		}

		
		exit();

	}
?>