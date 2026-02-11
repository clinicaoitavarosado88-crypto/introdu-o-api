<?php
include_once('includes/connection.php');

$idresultados=0;
$idstatus=2;
$senha_net='';
// constantes	
$usu_gravac = $_COOKIE["cpf_usuario"] ?? 'SISTEMA';
$dat_gravac = date("m/d/Y H:i:s");
$usu = $_COOKIE['log_usuario'] ?? 'SISTEMA';



$sql233 = "select * from SENHA where log_usuari='$usu'";
$r233 = ibase_query($conn, $sql233);
$row233 = ibase_fetch_object($r233);
$idcidade = $row233 ? $row233->IDCIDADE : 1;

if (isset($_GET["idresultados"])) $idresultados=$_GET["idresultados"];

$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'; // Obter o IP do cliente

// Definir um cookie com uma identificaùùo ùnica se ele ainda nùo existir
if (!isset($_COOKIE['device_id'])) {
    $device_id = uniqid('device_', true);  // Gera um ID ùnico
    setcookie('device_id', $device_id, time() + (86400 * 30), "/");  // Cookie vùlido por 30 dias
} else {
    $device_id = $_COOKIE['device_id'];
}

// Funùùo para gravar o log
function writeLog($usu, $dat_gravac, $idresultados, $ip, $device_id) {
    $logFile = 'execution_log.txt'; // Arquivo onde o log serù armazenado
    $message = "Usu·rio: $usu | Data/Hora: $dat_gravac | IDResultado: $idresultados | IP: $ip | DeviceID: $device_id\n";
    
    // Abre o arquivo em modo de escrita (anexando ao final)
    $file = fopen($logFile, 'a');
    
    // Escreve a mensagem no arquivo de log
    fwrite($file, $message);
    
    // Fecha o arquivo apùs a escrita
    fclose($file);
}

// Registro do log da execuùùo
writeLog($usu, $dat_gravac, $idresultados, $ip, $device_id);

if ($idresultados>0){
	$sql="select a.*, p.paciente,p.cpf, p.email, p.endereco, p.numero, p.cidade, p.bairro, p.fone1, p.mae, p.aniversario, p.uf, p.sexo,
		c.convenio as nm_convenio,u.unidade as nm_unidade,
		m.nome as nm_medico, m.crm as crm_medico, m.uf as uf_medico,
		p.rg as nm_rg,p.orgaoemissor as nm_orgaoemissor,p.profissao as nm_profissao, a.idunidade,
		a.usu_gravac as usu_insert
		
		from LAB_RESULTADOS a
		left join lab_pacientes p  on p.idpaciente=a.idpaciente
		left join lab_convenios c on c.idconvenio=a.idconvenio
		left join lab_unidades u on u.id=a.idunidade
		left join lab_medicos_pres m on m.id=a.idmedico
		where a.idresultado=$idresultados ";
		$r=ibase_query($conn, $sql);
		$row=ibase_fetch_object($r);

		$idposto=$row->IDPOSTO;
		$idresultado=$idresultados;
		$idunidade = validar($row->IDUNIDADE);
		$senha_net=$row->SENHA_NET;

		$sql_verificacao = "select * from LAB_POSTOS where id=$idposto";
		$r_verificacao = ibase_query($conn, $sql_verificacao);
		$row_verificacao = ibase_fetch_object($r_verificacao);
		$idlab_cidade = $row_verificacao->IDLAB_CIDADE;

		//imprime o pdf
		require_once("../../fpdf/fpdf.php");
		class PDF extends FPDF
		{
		function Header() 
		{

		$this->SetLeftMargin(10);
		$this->SetFont('Arial','B',9);
		$this->Cell(100,1,"CLINICA OITAVA ROSADO",0,1,'C');
		//$this->Image($tempFile, $ximg, $yimg, $widthImg, $heightImg, $imageType);
		$this->Cell(150,5,'',0,1,'C');
		$this->SetFont('Arial','',7);		
		$this->Cell(170,20,'_______________________________________',0,1,'C');
		$this->SetFont('Arial','B',7);
		$this->SetX("9");
		$this->MultiCell(0,20,substr($this->title2,0,50). ' - ('.$this->title2e.')',0,1);
		$this->SetFont('Arial','B',7);	
		$this->SetX("5");
		$this->Cell(70,7,'OS:' .StrZero($this->title6,10),0,0,'L');	
		$this->SetFont('Arial','',7);	
		$this->Cell(240,7,'Atend.:' .$this->title1,0,1,'L');	
		$this->SetFont('Arial','',7);			
		if ($this->title2b==11){
			$this->Cell(240,12,'RG: '.$this->title2c,0,1,'L');
			$this->Cell(240,12,'CPF: '.$this->title2d,0,1,'L');
		}	
		$this->SetX("5");
		$this->Multicell(0,10,'ConvÍnio: '.substr($this->title5,0,44));
		$this->SetX("5");
		$this->Cell(100,12,'Atendente: '.$this->title8,0,1,'L');
		$this->SetX("5");
		$this->Multicell(0,12,'Grupo: '.$this->title10);
		$this->SetX("5");
		$this->Multicell(0,12,'Tel.: '.$this->title3bb);
		$this->SetX("5");
		$this->Cell(240,12,'Entre.: '.$this->title66,0,1,'L');
		$this->SetX("5");
		$this->Cell(240,12,'Data de Nascimento: '.$this->title7,0,1,'L');
		$this->SetX("5");
		$this->Multicell(0,10,'MÈdico Solicitante: '.substr($this->title9,0,40),0,1);
		
		
		$this->Ln(10);
		//cabeùalho da tabela
		$this->SetFont('arial','',7);
		$this->SetX("5");
		$this->Cell(20,12,'N∫',0,0,"C");
		$this->Cell(30,12,'COD.TUSS',0,0,"C");
		$this->Cell(100,12,'EXAMES',0,0,"C");
	/*	$this->Cell(20,12,'Med. Exec.',0,0,"C");*/
		$this->Cell(40,12,'QUANT',0,1,"C");
		}

		function Footer() 
		{
		
		$this->SetLeftMargin(10);			
		//$this->SetFont('Arial','I',8);
		//$this->Cell(0,0,'Pagina '.$this->PageNo().'/{nb}',0,1,'C');
		//$this->SetY(-15);		
		//$this->Cell(0,0,'',0,0,'C');
		}
	}	
	
	//*********************************************
	
	$pdf= new PDF("P","pt",array(200,700));
	$pdf->title1=dataBR($row->DIAEXAME).' '.substr($row->HORAEXAME,0,5);
	$pdf->title2=$row->PACIENTE;
	$pdf->title2a=$row->MAE;
	$pdf->title2b=$row->IDUNIDADE;
	$pdf->title2c=$row->NM_RG.' '.$row->NM_ORGAOEMISSOR;
	$pdf->title2d=$row->CPF;
	$pdf->title2e=$row->IDPACIENTE;
	$pdf->title3=$row->ENDERECO.', '.$row->NUMERO;
	$pdf->title3a=$row->BAIRRO;
	$pdf->title4=$row->CIDADE.'/'.$row->UF;
	$pdf->title3bb=$row->FONE1.' - E: '.$row->EMAIL;
	$pdf->title5=utf8_decode($row->NM_CONVENIO);
	$pdf->title5a=$row->NM_PROFISSAO;
	$pdf->title6=$idresultados;
	$pdf->title66=$row->FORMA_ENVIO;
	$pdf->title7=dataBR($row->ANIVERSARIO).'  '.calcage(date("d/m/Y"),dataBR($row->ANIVERSARIO)).'   Sexo: '.$row->SEXO;
	$pdf->title8=utf8_decode($row->USU_INSERT);
	$pdf->title9=substr(utf8_decode($row->NM_MEDICO).' CRM: '.$row->CRM_MEDICO.'/'.$row->UF_MEDICO,0,30);
	$pdf->title10=utf8_decode($row->NM_UNIDADE);

	$pdf->SetLeftMargin(40);
	$pdf->SetAutoPageBreak(true,32);
	$pdf->AliasNbPages();
	$pdf->Open();
	$pdf->AddPage();
	$pdf->SetFont('Arial','',8);
	$i=1;
	$sql2="select a.*,e.exame as nm_exame,t.cod_tuss as nm_cod_tuss,
	m.nome as nm_medico_exe,e.imprime_etq as nm_imprime_etq, e.idsetor,
	e.imprime_net as nm_imprime_net, s.setor as nm_setor
	from lab_itemresultados a
	left join lab_resultados b on b.idresultado=a.idresultados
	left join lab_medicos_pres m on m.id=a.idmedico_exe
	left join lab_exames e on e.idexame=a.idexame
	left join lab_setor  s on s.idsetor=e.idsetor	
	left join lab_conveniostab_it t on (t.idexame=a.idexame and t.idconvenio=b.idconvenio)
	where idresultados=$idresultados and a.ai=1 order by s.ordem, s.setor, e.ordem_impressao";
	$r2=ibase_query($conn, $sql2);
	$imprime_net=0;	
	$pdf->SetFont('Arial','',6);			
	$tem_31=0;	
	while($row2=ibase_fetch_object($r2)){

		$array=array("1","4","5","6");
		if (in_array($row2->IDSETOR, $array)){
			$tem_31=1;
		}

		// imprime resultados via internet para COVID e todas as culturas -  Aparecida 06/08/2020
		if ($row2->NM_IMPRIME_NET=='S'){
			$imprime_net=1;
		}
		
		if ($i % 2)
			{
			//$pdf->SetFillColor(212,208,050);
			}
		else
			{
			//$pdf->SetFillColor(212,208,100);
			}	
		$pdf->SetX("1");
		$pdf->Cell(20,12,$i,0,0,"C",0);
		$pdf->SetFont('Arial','B',5);	
		$pdf->Cell(20,12,$row2->NM_COD_TUSS,0,0,"C",0);
		$pdf->SetFont('Arial','',6);	
		$pdf->Cell(100,12,substr($row2->NM_EXAME,0,50),0,0,"L",0);
		/*$pdf->Cell(100,12,substr($row2->NM_MEDICO_EXE,0,40),0,0,"L",0);*/
		$pdf->Cell(60,12,tran0($row2->QUANT),0,1,"C",0);
		//$pdf->Cell(60,15,tran($row->PA_SA),1,1,"R",0);
		//$pdf->Cell(60,15,'-',1,1,"C",0);
		$i++;
		}
	$pdf->SetX("1");
	$mensagem="ApÛs marcaÁ„o do exame, o paciente tem 24 horas para desistÍncia e/ou remarcar o exame sem nenhum Ùnus. Depois deste perÌodo, ser„o cobrados os custos administrativos.";
	$pdf->SetFont('Arial','',7);
	$pdf->Ln(6);	
	$pdf->MultiCell(0,10,'Obs.: '.$mensagem,0,'J',0);	
	$pdf->Ln(6);

	$pdf->SetX("1");
	$mensagem="O orÁamento È v·lido por 3 dias corridos, caso n„o realize o pagamento dentro desse perÌodo, o valor poder· sofrer alteraÁ„o.";
	$pdf->SetFont('Arial','',7);
	$pdf->Ln(6);	
	$pdf->MultiCell(0,10,'Obs.: '.$mensagem,0,'J',0);	
	$pdf->Ln(6);
	/*if ($idexame = 2809){
		$pdf->Cell(480,10,"Prazo de Entrega 3 dias ùteis.",0,1,'L');
	}*/
	if ($imprime_net>0){
		/*$mensagem2 ="Clùnica Oitava Rosado";*/
		$mensagem3 ="Informativo";
		$mensagem2 ="COMO RETIRAR SEU LAUDO";
		/*$mensagem4="Para retirar seus exames pela internet, acesse o nosso site www.clinicaoitavarosado.com.br";*/
		$mensagem5='Acesse nosso site e Clique em "Resultado de Exames", Digite o Login e senha que se encontram abaixo.';
		$mensagem6="Presencial";
		$mensagem7="Documento de identificaÁ„o do 
		paciente ou protocolo do exame.";
		$matriz="Rua Juvenal Lamartine, 119 - Centro, 59600-155
		Segunda a Sexta, 06h ‡s 18h, 
		S·bado de 06:30h ‡s 11h
		www.clinicaoitavarosado.com.br
		(84) 3315-6900
		";
		$matriz2="Rua Presidente MÈdici, 256 - IgapÛ, 59106-000
		Segunda a Sexta, 06h ‡s 17:30h
		www.clinicaoitavarosado.com.br
		(84) 3315-6900
		";
		$matriz3="Av. Bela Parnamirim, 880 - Parque de ExposiÁıes - Parnamirim - RN, 59147-060
		Segunda a Sexta, 06h ‡s 17h
		www.clinicaoitavarosado.com.br
		(84) 3315-6900
		";
		$matriz4="Rua Expedito Alves, RN 015 - Centro, 59695-000
		Segunda a Sexta, 06h ‡s 17h
		www.clinicaoitavarosado.com.br
		(84) 3315-6900
		";
		$matriz5="Av. Sen. Jo„o Severiano da C‚mara, 1304 - Centro, 59650-000
		Segunda a Sexta, 06h ‡s 17:48h
		www.clinicaoitavarosado.com.br
		(84) 3315-6900
		";
	
		$xi = 16; // Altere para a posiùùo desejada
		$yi = 306; // Altere para a posiùùo desejada
		
		// Largura e altura da caixa que envolve o texto
		$boxWidth = 150; // Altere para a largura desejada
		$boxHeight = 15; // Altere para a altura desejada
						
		$pdf->SetFont('Arial','B',9);
		$pdf->MultiCell(0,10,''.$mensagem2 ,0,'C',0);	
		$pdf->Ln(3);
		$pdf->SetFont('Arial','B',7);
		/*$pdf->MultiCell(0,10,''.$mensagem4 ,0,'C',0);	
		$pdf->Ln(3);*/
		$pdf->MultiCell(0,10,''.$mensagem5,0,'C',0);	
		$pdf->Ln(3);
		$pdf->MultiCell(0,10,"Login:"  .$idresultados. ' - Senha: ' .$senha_net,0,'C',0);
		$pdf->MultiCell(0,10,''.$mensagem6,0,'C',0);	
		$pdf->Ln(3);
		$pdf->MultiCell(0,10,''.$mensagem7,0,'C',0);	
		$pdf->Ln(3);
		if($idlab_cidade == 1){
		$pdf->SetFont('Arial','',7);
		$pdf->MultiCell(0,10,''.$matriz,0,'C',0);
		}
		if($idlab_cidade == 2){
			$pdf->SetFont('Arial','',7);
			$pdf->MultiCell(0,10,''.$matriz2,0,'C',0);
		}
		if($idlab_cidade ==3){
			$pdf->SetFont('Arial','',7);
			$pdf->MultiCell(0,10,''.$matriz3,0,'C',0);
		}
		if($idlab_cidade == 4){
			$pdf->SetFont('Arial','',7);
			$pdf->MultiCell(0,10,''.$matriz4,0,'C',0);
		}
		if($idlab_cidade == 5){
			$pdf->SetFont('Arial','',7);
			$pdf->MultiCell(0,10,''.$matriz5,0,'C',0);
		}

		
	}
	$pdf->Ln(10);

	ob_start();
	$pdf->Output("arquivo.pdf","I");
		// Exclua o arquivo temporùrio apùs o uso
	unlink($tempFile);
	$tem_31=1;
	
	// se for exame laboratorial, gera xml do interfaceamento
	// gera o xml para unidades externas
	if ($tem_31>0){
		// como ja esta no idsetor in (1,4,5,6),
		// ler variavel se posto (externo) imprimi ou nao etiquetas
		
		$sql_imp="select imprime_etq from lab_postos where id=$idposto and id not in (1,10)";
		$r_imp=ibase_query($sql_imp);
		$row_imp=ibase_fetch_object($r_imp);
		
		/*
		$people=array("1","10","3");
		if (in_array($idposto, $people)){
		*/
		
		// gera impressao de etiquetas de posto de coleta
			
		if ($row_imp->IMPRIME_ETQ=='S'){

			// conta os exames por setor para impressao somente de uma etiqueta por setor
			// ex 837340 (3 exames)
			// ex 836702 Zona Norte (15 exames)
			
			$sql99="select a.idresultados, e.idsetor, s.setor as nm_setor, p.paciente,
			b.diaexame,count(*) as total
            from lab_itemresultados a
            left join lab_resultados b on b.idresultado=a.idresultados
            left join lab_pacientes p on p.idpaciente=b.idpaciente
            left join lab_exames e on e.idexame=a.idexame
            left join lab_setor  s on s.idsetor=e.idsetor
            where a.idresultados=$idresultados and a.ai=1 and s.imprime_etq='S'
            and e.imprime_etq='S'
            group by 1,2,3,4,5";
			
			$r99=ibase_query($sql99);
			while($row99=ibase_fetch_object($r99)){				
				$paciente=$row99->PACIENTE;
				$diaexame=$row99->DIAEXAME;
				$idsetor=$row99->IDSETOR;
				$nm_setor=$row99->NM_SETOR;
				$new_id=substr(md5(uniqid(false)),1,13);

				// ler as abreviaturas dos exames para colocacao na etiqueta
				$sql98="select e.abrev
				from lab_itemresultados a
				left join lab_resultados b on b.idresultado=a.idresultados
				left join lab_pacientes p on p.idpaciente=b.idpaciente
				left join lab_exames e on e.idexame=a.idexame
				left join lab_setor  s on s.idsetor=e.idsetor
				where a.idresultados=$idresultados and a.ai=1 and e.idsetor=$idsetor
				and e.imprime_etq='S' order by s.ordem";
				$exames='';
				$r98=exeBD($sql98);  //usar exeBD aqui
				while ($row98=ibase_fetch_object($r98)){
					$exames.=$row98->ABREV.', ';
				}
				
				$sql999="insert into serveti(id,idresultado,idposto,usu_gravac,dat_gravac,ai,
				idsetor,nm_setor,diaexame,paciente,exames) 
				values('$new_id',$idresultados, $idposto, '$usu_gravac', '$dat_gravac',1,
				$idsetor, '$nm_setor', '$diaexame','$paciente','$exames')";
				//echo $sql."<p>";
				
				//21-01-2021 - funùùo somente neste servidor IP 250 lendo 187.19.xxx.xxx pois
				//so sera executada em postos de fora da oitava
				exeBDposto($sql999);					
				
			}	
		}
		
		// se for medicina
		if ($idposto==10){
			$sql="insert into servimp_med(idresultado,idposto,usu_gravac,dat_gravac,ai) 
			values($idresultado,$idposto,'$usu_gravac','$dat_gravac',1)";
		}else{
			$sql="insert into servimp(idresultado,idposto,usu_gravac,dat_gravac,ai) 
			values($idresultado,$idposto,'$usu_gravac','$dat_gravac',1)";
		}
		
		//echo $sql."<p>";
		//ibase_query($sql); //estava sendo anulada por exeBD999() na linha 220
		exeBD($sql);
		$idstatus=3;					
	
		//armazena idresultado para geraùùo de xml
		$sql9="select count(*) as total from serveti where ai=1 and idresultado=$idresultado";
		$r9=ibase_query($sql9);
		$row9=ibase_fetch_object($r9);
		if ($row9->TOTAL==0){
			$sql="insert into serveti(idresultado,idposto,usu_gravac,dat_gravac,ai) 
			values($idresultado,$idposto,'$usu_gravac','$dat_gravac',1)";
			//echo $sql."<p>";
			ibase_query($sql);
			$idstatus=4;					
		}
	}	
	
	ibase_query($conn, "update lab_resultados set impresso='S',idstatus=$idstatus where idresultado=$idresultados ");
}

// FunÁıes auxiliares necess·rias para a agenda
function validar($value) {
    return $value ?? 0;
}

function dataBR($date) {
    if ($date) {
        $timestamp = strtotime($date);
        return date('d/m/Y', $timestamp);
    }
    return '';
}

function calcage($date1, $date2) {
    $diff = abs(strtotime($date1) - strtotime($date2));
    $years = floor($diff / (365*60*60*24));
    return $years . ' anos';
}

function tran0($value) {
    return number_format($value, 0);
}

function StrZero($num, $length) {
    return str_pad($num, $length, '0', STR_PAD_LEFT);
}

function exeBD($sql) {
    global $conn;
    return ibase_query($conn, $sql);
}

function exeBDposto($sql) {
    global $conn;
    return ibase_query($conn, $sql);
}

function exeBDpacs($sql) {
    global $conn;
    return ibase_query($conn, $sql);
}

function exeBDpacszn($sql) {
    global $conn;
    return ibase_query($conn, $sql);
}

function exeBDpacsparnamirim($sql) {
    global $conn;
    return ibase_query($conn, $sql);
}

function exeBDpacsassu($sql) {
    global $conn;
    return ibase_query($conn, $sql);
}

?>
