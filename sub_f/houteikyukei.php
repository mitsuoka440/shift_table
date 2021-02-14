<?php
function Houteikyukei($kaisi,$syuryou,$n_kaisi,$n_syuryou){
//変数の初期化
    $inj=0;$inf=0;$in_t=0;
    $outj=0;$outf=0;$out_t=0;
    $n_inj=0;$n_inf=0;$n_in_t=0;
    $n_outj=0;$n_outf=0;$n_out_t=0;
    $bkj=0;$bkf=0;$bk_t=0;
    $diff = 0;
    $kyu_kasan=0;//通常休憩に加算するための変数

    //DATEなどではうまくいかず。文字列として扱い、切り出して、数値へ変換することにした。
    $inj = intval(date("H",strtotime($kaisi)));//1回目出勤時間
    $inf = intval(date("i",strtotime($kaisi)));
    $outj = intval(date("H",strtotime($syuryou)));
    $outf = intval(date("i",strtotime($syuryou)));
    //計算しやすいように時間を分に換算
    $in_t = $inj*60 + $inf;
    $out_t = $outj*60 + $outf;
      //出勤２に勤務がある場合。
      //しかし、勤怠データからの読み込みでゼロが表示されてしまうので、出勤と退勤データが違う場合という条件を追加
      if ($n_kaisi !== "" && $n_syuryou !=="" && $n_kaisi !== $n_syuryou){
        $n_inj = intval(date("H",strtotime($n_kaisi)));//1回目出勤時間
        $n_inf = intval(date("i",strtotime($n_kaisi)));
        $n_outj = intval(date("H",strtotime($n_syuryou)));
        $n_outf = intval(date("i",strtotime($n_syuryou)));
        $n_in_t = $n_inj*60+$n_inf;
        $n_out_t = $n_outj*60+$n_outf;
          if ($n_in_t <= $out_t){//2回目出勤が日をまたいだとき
            $n_in_t=$n_in_t+24*60;
          }
          if ($n_in_t-$out_t>=1){//1回目と2回目勤務に1時間以上間があった場合は、休憩として加算する。
            $kyu_kasan = $n_in_t - $out_t ;
          }
         $out_t = $n_out_t;//2回目の終了時間が1勤務の終了時間とする
       }
//休憩時間の計算 6時間を超えて8時間未満45分、8時間以上1時間
//拘束時間が6時間を超えて、45分休憩を取ると労働時間が6時間を下回る場合、6時間になるように休憩を設定

       if ( $out_t <= $in_t ){
         $out_t = $out_t + 24*60;
       }

       $diff = $out_t - $in_t;

       if ($diff >= 9*60) {
         $kyukei = sprintf("%02d",floor(($kyu_kasan+60)/60)).":".sprintf("%02d",$kyu_kasan%60);
       }else if ($diff >= 6.75*60) {//45分とらなければならないギリギリのライン
         $kyukei = "00:45";
       }else if ($diff >6*60){
         $diff = ($diff-6*60);
         $kyukei = "00:". sprintf("%02d",$diff);
       }else {
         $kyukei = "00:00";
       }

       return ($kyukei);
    }
