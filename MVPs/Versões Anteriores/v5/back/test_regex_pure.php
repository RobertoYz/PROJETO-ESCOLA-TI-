<?php
$t = "O orçamento máximo a ser solicitado na modalidade Auxílio à Pesquisa Regular é de R$ 600 mil, excluindo-se os valores";
var_dump(preg_match('/(?:global|total|investimento|aporte|recursos|fomento|valor máximo|é de)[^\d]{0,40}R\$\s*([0-9.,]+)\s*(milh[õo]es|mil)?/iu', $t, $m));
var_dump($m);
