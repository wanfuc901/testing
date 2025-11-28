<?php
$loaderClass = (isset($role) && $role === 'admin') ? 'vc-loader admin' : 'vc-loader';
?>
<link rel="stylesheet" href="public/assets/css/style.css">

<div id="vcLoader" class="<?= $loaderClass ?>">
  <div class="vc-reel">
    <div class="vc-reel-inner">
      <div class="vc-reel-hole"></div>
      <div class="vc-reel-light"></div>
    </div>
    <div class="vc-reel-strip"></div>
  </div>
  <div class="vc-text"><?= ($role === 'admin' ? 'VinCine Admin' : 'VinCine Studios') ?></div>
</div>

<script>
(function(){
  const loader=document.getElementById('vcLoader');
  if(!loader)return;
  const start=Date.now();
  function hide(){
    const elapsed=Date.now()-start;
    const delay=Math.max(0,700-elapsed);
    setTimeout(()=>{
      loader.classList.add('hidden');
      document.body.classList.remove('vc-lock');
    },delay);
  }
  document.body.classList.add('vc-lock');
  if(document.readyState==='complete') hide();
  else window.addEventListener('load',hide);
  window.VCLoading={show:()=>{loader.classList.remove('hidden');document.body.classList.add('vc-lock');},hide:hide};
})();
</script>
