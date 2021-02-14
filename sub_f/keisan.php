<?php
function Keisan($kaisi,$syuryou,$kyukei,$n_kaisi,$n_syuryou){
//変数の初期化
    $bkj=0;$bkf=0;

    $koj=0;$kof=0;
    $yinj=0;$yinf=0;
    $youtj=0;$youtf=0;
    $soyj=0;$soyf=0;
    $zinj=0; $zinf=0;
    $yzinj=0; $yzinf=0;
    $yzoutj=0; $yzoutf=0;
    $sozaj=0; $sozaf=0;
    $yzaj=0; $yzaf=0;
    $ykj=0; $ykf=0;
    $fkj=0; $fkf=0;
    $in_t=0; $out_t=0;
    $n_fl=0; $kasan=0; $sa=0;

     //2回目勤怠データがあるかの判断。０が入力される場合もあるので、出勤と退勤データが違う場合という条件を追加
     if ($n_kaisi !== "" && $n_syuryou !=="" && $n_kaisi !== $n_syuryou){
     $n_fl=1;//2回目出勤のある行を1回ループさせるための値
     }
     $a=0;//ループカウンタ
    for($a=0; $a<=$n_fl; $a++){//1日で2回出勤の場合、2回ループ処理。

      if ($a == 0){//0のときは1回出勤、1のとき2回出勤
      //時間の切り出し
        $inj = intval(date("H",strtotime($kaisi)));//1回目出勤時間
        $inf = intval(date("i",strtotime($kaisi)));
        $outj = intval(date("H",strtotime($syuryou)));
        $outf = intval(date("i",strtotime($syuryou)));
        if($kyukei!=""){
        $bkj = intval(date("H",strtotime($kyukei)));//休憩時間
        $bkf = intval(date("i",strtotime($kyukei)));//休憩分
        }
      }else{
        $inj = intval(date("H",strtotime($n_kaisi)));//2回目出勤時間
        $inf = intval(date("i",strtotime($n_kaisi)));
         //休憩に加算されている、1回目と2回目の間隔時間を調べる
         //inに2回目出勤時間が入り、outに1回目の時間が残っているタイミングで間隔をチェック
         $in_t = $inj*60 + $inf;
         $out_t = $outj*60 + $outf;
         if ($in_t <= $out_t){//2回目出勤が日をまたいだとき
           $in_t = $in_t + 24 * 60;
         }
         if ($in_t - $out_t >= 1){//1回目と2回目勤務に1時間以上間があった場合は、休憩として加算されている
           $kasan = $in_t - $out_t ;
         }
         //2回目勤務退勤時間を代入
        $outj = intval(date("H",strtotime($n_syuryou)));
        $outf = intval(date("i",strtotime($n_syuryou)));
      }
       if (($outj + $outf / 60) < ($inj + $inf / 60)){
//拘束時間の計算（退勤時間のほうが数字が小さい時：日付をまたいだ場合）
         $sa = (($outj + 24)* 60 + $outf)-($inj * 60 + $inf);
         $koj =  $koj + floor($sa / 60);
         $kof = $kof + $sa % 60;
         //夜勤の開始時間の計算
                 if ($inj < 22){
                  $yinj=22;
                  $yinf=0;
                 }else{
                  $yinj=$inj;
                  $yinf=$inf;
                 }
           //夜勤の終了時間の計算
                 if($outj >= 5){//5+24で２９
                    $youtj = 5+24;
                    $youtf = 0;
                 }else{
                    $youtj=$outj+24;
                    $youtf=$outf;
                 }
       }else{
//拘束時間の計算（通常）
         $sa = ($outj*60+$outf)-($inj*60+$inf);
         $koj = $koj + floor($sa/60);
         $kof = $kof + $sa%60;
         //夜勤の開始時間の計算
               if ($inj >= 22 && $outj >=22 ){
                  $yinj=$inj;
                  $yinf=$inf;
                  $youtj=$outf;
                  $youtf=$outf;
               }
               if ( $inj < 5 && $outj <5){
                  $yinj=$inj;
                  $yinf=$inf;
                  $youtj=$outj;
                  $youtf=$outf;
               }
               if ( $inj < 22 && ($outj+$outf/60) > 22){
                  $yinj = 22;
                  $yinf = 0;
                  $youtj = $outj;
                  $youtf = $outf;
               }
               if ( $inj < 5 && ($outj+$outf/60) >= 5){
                  $yinj = $inj;
                  $yinf = $inf;
                  $youtj = 5;
                  $youtf = 0;
               }
       }

//総夜勤時間の計算
      $soyj= $soyj + floor((($youtj*60 + $youtf ) - ($yinj*60 + $yinf))/60);
      $soyf= $soyf + (($youtj*60 + $youtf ) - ($yinj*60 + $yinf))%60;
  }//勤務回数ループ終了

//2回目勤務用に加算された休憩を引く
     $bkj = $bkj - floor($kasan/60);
     $bkf = $bkf - $kasan%60;

//総勤務時間の計算（拘束時間から休憩時間を引く）
     $soj = floor((($koj*60+$kof)-($bkj*60+$bkf))/60);
     $sof = (($koj*60+$kof)-($bkj*60+$bkf))%60;

//総残業時間の計算  ８時間　=　４８０分
         if (($soj*60+$sof) <= 480 ){
         //勤務時間が８時間以下とき、残業０
          $sozaj=0;$sozaf=0;
         }else{
         //勤務時間が８時間より多い時
         $sozaj=floor(($soj*60+$sof-480)/60);
         $sozaf=($soj*60+$sof-480)%60;
         }

//残業の開始時間を調べる（終了時間から総残業時間分さかのぼる）
//退勤時刻と残業時間合計を比べることで、残業中に日付をまたいだかわかる。
    if ( $sa >= $sozaj*60+$sozaf ){//2回目勤務時間と総残業を比べて2回目勤務がながければ通常
        if (($outj+$outf/60) < ($sozaj+$sozaf/60)){
          $zinj = floor(((($outj+24)*60+$outf)-($sozaj*60+$sozaf))/60);
          $zinf = ((($outj+24)*60+$outf)-($sozaj*60+$sozaf))%60;
        }else{
          $zinj = floor((($outj*60+$outf)-($sozaj*60+$sozaf))/60);
          $zinf = ((($outj)*60+$outf)-($sozaj*60+$sozaf))%60;//?
        }
//夜勤残業があるか調べる
        if (($outj*60+$outf) < ($zinj*60+$zinf)){
          if($zinj < 22 ){
            $yzinj = 22;
            $yzinf = 0;
          }else{
            $yzinj = $zinj;
            $yzinf = $zinf;
          }
          if($outj >=5){
            $yzoutj = 29;
            $yzoutf = 0;
          }else{
            $yzoutj = $outj + 24;
            $yzoutf = $outf;
          }
        }else{
          if ($zinj >= 22 && $outj >=22){
            $yzinj = $zinj;
            $yzinf = $zinf;
            $yzoutj = $outj;
            $yzoutf = $outf;
          }
          if ($zinj < 5 && $outj < 5){
            $yzinj = $zinj;
            $yzinf = $zinf;
            $yzoutj = $outj;
            $yzoutf = $outf;
          }
          if ($zinj < 22 && ($outj*60+$outf) > 22*60){
            $yzinj = 22;
            $yzinf = 0;
            $yzoutj = $outj;
            $yzoutf = $outf;
          }
          if ($zinj < 5 && ($outj*60+$outf) >= 300){
            $yzinj = $zinj;
            $yzinf = $zinf;
            $yzoutj = 5;
            $yzoutf = 0;
          }
        }
        //夜勤残業時間の計算
        $yzaj = floor((($yzoutj*60+$yzoutf)-($yzinj*60+$yzinf))/60);
        $yzaf = (($yzoutj*60+$yzoutf)-($yzinj*60+$yzinf))%60;

      }else{//2回目勤務時間が総残業時間より短い場合
      for ($a=0;$a<2;$a++){
        if (($outj*60+$outf) < $sa ){
          $zinj = floor(((($outj+24)*60+$outf)-$sa)/60);
          $zinf = ((($outj+24)*60+$outf)-$sa)%60;
        }else{
          $zinj = floor((($outj*60+$outf)-$sa)/60);
          $zinf = ((($outj)*60+$outf)-$sa)%60;//??
        }
//夜勤残業があるか調べる
        if (($outj*60+$outf) < ($zinj*60+$zinf)){
          if($zinj < 22 ){
            $yzinj = 22;
            $yzinf = 0;
          }else{
            $yzinj = $zinj;
            $yzinf = $zinf;
          }
          if($outj >=5){
            $yzoutj = 29;
            $yzoutf = 0;
          }else{
            $yzoutj = $outj + 24;
            $yzoutf = $outf;
          }
        }else{
          if ($zinj >= 22 && $outj >=22){
            $yzinj = $zinj;
            $yzinf = $zinf;
            $yzoutj = $outj;
            $yzoutf = $outf;
          }
          if ($zinj < 5 && $outj < 5){
            $yzinj = $zinj;
            $yzinf = $zinf;
            $yzoutj = $outj;
            $yzoutf = $outf;
          }
          if ($zinj < 22 && ($outj*60+$outf) > 22*60){
            $yzinj = 22;
            $yzinf = 0;
            $yzoutj = $outj;
            $yzoutf = $outf;
          }
          if ($zinj < 5 && ($outj*60+$outf) >= 300){
            $yzinj = $zinj;
            $yzinf = $zinf;
            $yzoutj = 5;
            $yzoutf = 0;
          }
        }
//夜勤残業時間の計算
$yzaj = $yzaj + floor((($yzoutj*60+$yzoutf)-($yzinj*60+$yzinf))/60);
$yzaf = $yzaf + (($yzoutj*60+$yzoutf)-($yzinj*60+$yzinf))%60;
if ($outj*60+$outf < $sa+$kasan){//1回目と2回目勤務の間隔をさかのぼる処理
 $outj = floor(((($outj+24)*60+$outf)-$sa-$kasan)/60);
 $outf =((($outj+24)*60+$outf)-$sa-$kasan)%60;
 $sa = $sozaj*60+$sozaf-$sa;
}else {
 $outj =floor((($outj*60+$outf)-$sa-$kasan)/60);
 $outf =(($outj*60+$outf)-$sa-$kasan)%60;
 $sa = $sozaj*60+$sozaf-$sa;
}
$zinj=0;$zinf=0;$yzinj=0;$yzinf=0;$yzoutj=0;$yzoutf=0;//2回目計算のため初期化
 }
}
 //普通休憩か深夜休憩かの判定をする
  $hantei = ($soyj*60+$soyf) / ($koj*60+$kof);

if ($hantei <= 0.5){
  //普通時間から休憩を引く
  $fkj = $bkj;$fkf = $bkf;
  $ykj = 0;$ykf = 0;
} else{
  //深夜時間から休憩を引く
  $fkj = 0;$fkf = 0;
  $ykj = $bkj;$ykf = $bkf;
}


//普通時間の計算(拘束時間-総残業時間-総夜勤時間+夜勤残業時間　-（休憩)）
$fj = floor((($koj*60+$kof)-($sozaj*60+$sozaf)-($soyj*60+$soyf)+($yzaj*60+$yzaf)-($fkj*60+$fkf))/60);
$ff = (($koj*60+$kof)-($sozaj*60+$sozaf)-($soyj*60+$soyf)+($yzaj*60+$yzaf)-($fkj*60+$fkf))%60;
$j = sprintf("%02d",$fj);
$f = sprintf("%02d",$ff);
$futuu = $j . ":" . $f;
//残業時間の計算（総残業時間-夜勤残業時間）
$zj = floor((($sozaj*60+$sozaf)-($yzaj*60+$yzaf))/60);
$zf = (($sozaj*60+$sozaf)-($yzaj*60+$yzaf))%60;
$j = sprintf("%02d",$zj);
$f = sprintf("%02d",$zf);
$zangyou = $j . ":" . $f;
//深夜時間の計算（総夜勤時間-夜勤残業時間-(休憩））
$sj = floor((($soyj*60+$soyf)-($yzaj*60+$yzaf)-($ykj*60+$ykf))/60);
$sf = (($soyj*60+$soyf)-($yzaj*60+$yzaf)-($ykj*60+$ykf))%60;
$j = sprintf("%02d",$sj);
$f = sprintf("%02d",$sf);
$sinya = $j . ":" . $f;
//深夜残業時間の入力
$j = sprintf("%02d",$yzaj);
$f = sprintf("%02d",$yzaf);
$sinyazan = $j . ":" . $f;

return array($futuu,$zangyou,$sinya,$sinyazan);
}
