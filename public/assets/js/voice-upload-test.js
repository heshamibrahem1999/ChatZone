
let chunks=[], blob=null, recorder=null;
const out=document.getElementById('out');
document.getElementById('rec').onclick=async()=>{
  chunks=[]; blob=null; out.textContent='asking microphone...';
  const stream=await navigator.mediaDevices.getUserMedia({audio:true});
  recorder=new MediaRecorder(stream, MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? {mimeType:'audio/webm;codecs=opus'} : undefined);
  recorder.ondataavailable=e=>{ if(e.data.size) chunks.push(e.data); };
  recorder.onstop=()=>{ stream.getTracks().forEach(t=>t.stop()); blob=new Blob(chunks,{type:'audio/webm'}); out.textContent='blob size: '+blob.size; document.getElementById('upload').disabled=false; };
  recorder.start(500); document.getElementById('stop').disabled=false; out.textContent='recording...';
};
document.getElementById('stop').onclick=()=>{ recorder.stop(); document.getElementById('stop').disabled=true; };
document.getElementById('upload').onclick=async()=>{
  const fd=new FormData(); fd.append('voice', blob, 'test.webm'); fd.append('csrf_token', document.getElementById('upload').dataset.csrf || '');
  const r=await fetch('voice_upload_test_endpoint.php',{method:'POST', body:fd, credentials:'same-origin'});
  out.textContent=await r.text();
};
