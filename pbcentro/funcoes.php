<?php
//seleciona todas as vendas do periodo e agrupa por classe
$loja   = "10";
function buscavendas($codloja,$datainicio, $datafim){
	require("conexaopdo.php");
	$status = ("F");
//Seleciona o valor bruto das vendas, já descontando os estornos de pre-venda, devendo apenas ser descontados as devolucoes da tabela devolucoes//
	$sdev = $con->prepare("select
vendaprodutos.CODPROD,
(SUM(vendaprodutos.VALORBRUTO)),
(SUM(vendaprodutos.VALORPRODUTO)),
(SUM(vendaprodutos.VALORSUBSIDIO)),
(SUM(vendaprodutos.CUSTOMEDIO)),
(SUM(vendaprodutos.ULTCUSTO)),
(SUM(vendaprodutos.QUANTPROD)),
(SUM(vendaprodutos.ARREDOND)),
(produtos.CODPROD),
(produtos.CODCLASSE),
(SUM(venda.VALORSUBSIDIO)),
(MIN(venda.VALORTOTAL)),
(MAX(venda.VALORTOTAL)),
venda.STATUS,
(COUNT(distinct venda.IDVENDA)),
classes.CODCLASS,
classes.NOMECLASS from vendaprodutos
inner join produtos on vendaprodutos.CODPROD = produtos.CODPROD
inner join venda on vendaprodutos.IDVENDA = venda.IDVENDA
inner join classes on produtos.CODCLASSE = classes.CODCLASS
WHERE (vendaprodutos.DATAHORAVENDA between ? and ?)
AND (venda.CODLOJA = ?)
AND (venda.STATUS = ?)
GROUP BY (produtos.CODCLASSE)");
		$sdev->bindValue(1,$datainicio);
		$sdev->bindValue(2,$datafim);
		$sdev->bindValue(3,$codloja);
		$sdev->bindValue(4,$status);
		$sdev->execute();
		$contador = 0;

	

		while($rowsdev = $sdev->fetch(PDO::FETCH_ASSOC)){

	//calculos por classe---------------------------------------------------------------
	$codigodaclasse[$contador]        = $rowsdev['CODCLASSE'];
	$nomedaclasse[$contador]          = $rowsdev['NOMECLASS'];
	$liquidoporclasse[$contador]      = $rowsdev['(SUM(vendaprodutos.VALORPRODUTO))'];
	$arredondpoclasse[$contador]      = $rowsdev['(SUM(vendaprodutos.ARREDOND))'];
	$brutoporclasse[$contador]        = $rowsdev['(SUM(vendaprodutos.VALORBRUTO))'];
	$customedioporclasse[$contador]   = $rowsdev['(SUM(vendaprodutos.CUSTOMEDIO))'];
	$subsidioporclasse[$contador]     = $rowsdev['(SUM(vendaprodutos.VALORSUBSIDIO))'];
	$maiorvendaporclasse[$contador]   = $rowsdev['(MAX(venda.VALORTOTAL))'];
	$menorvendaporclasse[$contador]   = $rowsdev['(MIN(venda.VALORTOTAL))'];
	//array list     --------------------------------------------------------------------
			$vendas[] = array(
				'codigo'    => $codigodaclasse[$contador],
				'nome'      => $nomedaclasse[$contador],
				'liquido'   => $liquidoporclasse[$contador],
				'arredond'  => $arredondpoclasse[$contador],
				'bruto'     => $brutoporclasse[$contador],
				'custo'     => $customedioporclasse[$contador],
				'subsidio'  => $subsidioporclasse[$contador],
				'maiorvenda'=> $maiorvendaporclasse[$contador],
				'menorvenda'=> $menorvendaporclasse[$contador]
							  );
			
			$contador++;

		};
			
	return $vendas;
};
//-----------------------Seleciona o total de atendimentos no mês, tendo em vista que agrupado por classe ele soma 2 vezes a mesma venda que contem classes diferentes-------------------------------------------//
function busca_atendimentos($codloja,$datainicio, $datafim){
	require("conexaopdo.php");
	$status = ("F");	
	$sdev = $con->prepare("select (COUNT(distinct venda.IDVENDA)) from venda
	WHERE (venda.DATAHORAVENDA between ? and ?)
	AND (venda.CODLOJA = ?)
	AND (venda.STATUS = ?) GROUP BY venda.CODIGOVENDEDORES");
	$sdev->bindValue(1,$datainicio);
	$sdev->bindValue(2,$datafim);
	$sdev->bindValue(3,$codloja);
	$sdev->bindValue(4,$status);
	$sdev->execute();
	$contador = 0;
	$atendimentosporvendedor = array();
	$totaldeatendimentos = 0;
	while($rowsdev = $sdev->fetch(PDO::FETCH_ASSOC)){
	
	$atendimentosporvendedor[$contador] = $rowsdev['(COUNT(distinct venda.IDVENDA))'];
	$totaldeatendimentos               += $atendimentosporvendedor[$contador]; 
};
	return $totaldeatendimentos;
};
//---------------------Seleciona todos as devoluções do Periodo-----------------------------------//
function busca_devolucoes($codloja, $datainicio,$datafim){
require("conexaopdo.php");
$sdev = $con->prepare("select
(SUM(devolucao.VALORBRUTO)),
(SUM(devolucao.VALORPRODUTO)),
(SUM(devolucao.VALORSUBSIDIO)),
(SUM(devolucao.CUSTOMEDIO)),
devolucao.CODLOJA,
devolucao.CODPROD,
produtos.CODPROD,
produtos.CODCLASSE
FROM devolucao
INNER JOIN produtos on devolucao.CODPROD = produtos.CODPROD
WHERE (devolucao.DATAHORADEVOLUC between ? and ?)
AND (devolucao.CODLOJA = ?)
group by produtos.CODCLASSE");
$sdev->bindValue(1,$datainicio);
$sdev->bindValue(2,$datafim);
$sdev->bindValue(3,$codloja);

$sdev->execute();
$contador = 0;
while($rowsdev = $sdev->fetch(PDO::FETCH_ASSOC)){
	
	$codigodaclassedevolucoes[$contador] = $rowsdev['CODCLASSE'];
	$devolucbruto[$contador]             = $rowsdev['(SUM(devolucao.VALORBRUTO))'];
	$devolucliquido[$contador]           = $rowsdev['(SUM(devolucao.VALORPRODUTO))'];
	$custodevoluc[$contador]             = $rowsdev['(SUM(devolucao.CUSTOMEDIO))'];
	
	//array list     --------------------------------------------------------------------
			$devolucoes[] = array(
				
				'codigo'            => $codigodaclassedevolucoes[$contador],
				'devolucbruto'      => $devolucbruto[$contador],
				'devolucliquido'    => $devolucliquido[$contador],
				'custodevoluc'      => $custodevoluc[$contador],			  
							  );
};
	

	if(isset($devolucoes)){
	return $devolucoes;
	}else{
		$devolucoes[0]['codigo'] = 99;
		$devolucoes[0]['devolucbruto'] = 0;
		$devolucoes[0]['devolucliquido'] = 0;
		$devolucoes[0]['custodevoluc'] = 0;		
		
	return $devolucoes;
		
	};
};
//----------------------seleciona todas as pre vendas estornadas no caixa
function buscaestornos($codloja,$datainicio, $datafim){
	require("conexaopdo.php");
	$status = ("C");
	$sdev = $con->prepare("select
vendaprodutos.CODPROD,
(SUM(vendaprodutos.VALORBRUTO)),
(SUM(vendaprodutos.VALORPRODUTO)),
(SUM(vendaprodutos.VALORSUBSIDIO)),
(SUM(vendaprodutos.CUSTOMEDIO)),
(SUM(vendaprodutos.ULTCUSTO)),
(SUM(vendaprodutos.QUANTPROD)),
(SUM(vendaprodutos.ARREDOND)),
(produtos.CODPROD),
(produtos.CODCLASSE),
(SUM(venda.VALORSUBSIDIO)),
(MIN(venda.VALORTOTAL)),
(MAX(venda.VALORTOTAL)),
venda.STATUS,
(COUNT(distinct venda.IDVENDA)),
classes.CODCLASS,
classes.NOMECLASS from vendaprodutos
inner join produtos on vendaprodutos.CODPROD = produtos.CODPROD
inner join venda on vendaprodutos.IDVENDA = venda.IDVENDA
inner join classes on produtos.CODCLASSE = classes.CODCLASS
WHERE (vendaprodutos.DATAHORAVENDA between ? and ?)
AND (venda.CODLOJA = ?)
AND (venda.STATUS = ?)
GROUP BY produtos.CODCLASSE");
		$sdev->bindValue(1,$datainicio);
		$sdev->bindValue(2,$datafim);
		$sdev->bindValue(3,$codloja);
		$sdev->bindValue(4,$status);
		$sdev->execute();
		$contador = 0;

	

		while($rowsdev = $sdev->fetch(PDO::FETCH_ASSOC)){

	//calculos por classe---------------------------------------------------------------
	$codigodaclasse[$contador]        = $rowsdev['CODCLASSE'];
	$nomedaclasse[$contador]          = $rowsdev['NOMECLASS'];
	$liquidoporclasse[$contador]      = $rowsdev['(SUM(vendaprodutos.VALORPRODUTO))'];
	$arredondpoclasse[$contador]      = $rowsdev['(SUM(vendaprodutos.ARREDOND))'];
	$brutoporclasse[$contador]        = $rowsdev['(SUM(vendaprodutos.VALORBRUTO))'];
	$customedioporclasse[$contador]   = $rowsdev['(SUM(vendaprodutos.CUSTOMEDIO))'];
	$subsidioporclasse[$contador]     = $rowsdev['(SUM(vendaprodutos.VALORSUBSIDIO))'];
	$maiorvendaporclasse[$contador]   = $rowsdev['(MAX(venda.VALORTOTAL))'];
	$menorvendaporclasse[$contador]   = $rowsdev['(MIN(venda.VALORTOTAL))'];
	//array list     --------------------------------------------------------------------
			$estornos[] = array(
				'codigo'    => $codigodaclasse[$contador],
				'nome'      => $nomedaclasse[$contador],
				'liquido'   => $liquidoporclasse[$contador],
				'arredond'  => $arredondpoclasse[$contador],
				'bruto'     => $brutoporclasse[$contador],
				'custo'     => $customedioporclasse[$contador],
				'subsidio'  => $subsidioporclasse[$contador],
				'maiorvenda'=> $maiorvendaporclasse[$contador],
				'menorvenda'=> $menorvendaporclasse[$contador]							  
							  );
			
			$contador++;

		};
	if(isset($estornos)){
		return $estornos;
	}else{
		$estornos[0]['liquido']=0;
		return $estornos;
	};	
	
};

function buscavendasvendedor($codloja,$datainicio, $datafim){
	require("conexaopdo.php");
	$status = ("F");
//Seleciona o valor bruto das vendas, já descontando os estornos de pre-venda, devendo apenas ser descontados as devolucoes da tabela devolucoes//
	$sdev = $con->prepare("select
(SUM(venda.VALORSUBSIDIO)),
(SUM(venda.VALORTOTAL)),
(SUM(venda.VALORBRUTO)),
venda.CODVEND,
(COUNT(distinct venda.IDVENDA)),
venda.STATUS,
vendedores.APELIDO
from venda
inner join vendedores on venda.CODVEND = vendedores.CODVEND
WHERE (venda.DATAHORAVENDA between ? and ?)
AND (venda.CODLOJA = ?)
AND (venda.STATUS = ?)
GROUP BY (venda.CODVEND)");
		$sdev->bindValue(1,$datainicio);
		$sdev->bindValue(2,$datafim);
		$sdev->bindValue(3,$codloja);
		$sdev->bindValue(4,$status);
		$sdev->execute();
		$contador = 0;

	

		while($rowsdev = $sdev->fetch(PDO::FETCH_ASSOC)){

	//calculos por classe---------------------------------------------------------------
	$codigovend[$contador]              = $rowsdev['CODVEND'];
	$apelido[$contador]                 = $rowsdev['APELIDO'];
	$vendavendedor[$contador]           = $rowsdev['(SUM(venda.VALORTOTAL))'];
	$atendivendedor[$contador]          = $rowsdev['(COUNT(distinct venda.IDVENDA))'];

	//array list     --------------------------------------------------------------------
			$vendasvendedor[] = array(
				'codigo'         => $codigovend[$contador],
				'apelido'		 => $apelido[$contador],
				'valor'          => $vendavendedor[$contador],
				'atendvend'      => $atendivendedor[$contador]
							         );
			
			$contador++;

		};
			
	return $vendasvendedor;
};

//----------------Seleciona todos as devoluções do Periodo e agrupa por vendedor----//
function busca_devolucoesvendedor($codloja, $datainicio,$datafim){
	require("conexaopdo.php");
$sdev = $con->prepare("select
(SUM(devolucao.VALORPRODUTO)),
devolucao.CODVEND
FROM devolucao
WHERE (devolucao.DATAHORADEVOLUC between ? and ?)
AND (devolucao.CODLOJA = ?)
group by devolucao.CODVEND;
");
$sdev->bindValue(1,$datainicio);
$sdev->bindValue(2,$datafim);
$sdev->bindValue(3,$codloja);

$sdev->execute();
$contador = 0;
while($rowsdev = $sdev->fetch(PDO::FETCH_ASSOC)){
	
	$codvend[$contador]                  = $rowsdev['CODVEND'];
	$devolucvend[$contador]              = $rowsdev['(SUM(devolucao.VALORPRODUTO))'];
	
	//array list     --------------------------------------------------------------------
			$devolucoes[] = array(
				
				'codvend'            => $codvend[$contador],
				'devolucvend'      => $devolucvend[$contador],	  
							  );
};
	

	if(isset($devolucoes)){
	return $devolucoes;
	}else{
		$devolucoes[0]['codvend'] = 0;
		$devolucoes[0]['devolucvend'] = 0;
		
	return $devolucoes;
		
	};
};
?>