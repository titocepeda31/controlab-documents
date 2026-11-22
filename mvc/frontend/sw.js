/* Aplicación sin conexión: cachea SOLO la interfaz estática; jamás sesiones, API, reportes o credenciales. */
const CACHE='demo-mecanica-suelo-v11';
const ROOT=new URL('./',self.registration.scope).pathname;
const SHELL=new URL('formulario.html',self.registration.scope).pathname;
const MANIFEST=new URL('manifest.json',self.registration.scope).pathname;
const ICON=new URL('icon.svg',self.registration.scope).pathname;
const ENTRIES=new Set([ROOT,SHELL,ROOT+'index.php']);
self.addEventListener('install',event=>event.waitUntil((async()=>{
 const cache=await caches.open(CACHE);
 await cache.addAll([SHELL,MANIFEST,ICON].map(url=>new Request(url,{cache:'reload'})));
 await self.skipWaiting();
})()));
self.addEventListener('activate',event=>event.waitUntil((async()=>{
 const keys=await caches.keys();
 await Promise.all(keys.filter(k=>k.startsWith('demo-mecanica-suelo-')&&k!==CACHE).map(k=>caches.delete(k)));
 await self.clients.claim();
})()));
self.addEventListener('fetch',event=>{
 const request=event.request,url=new URL(request.url);
 if(request.method!=='GET'||url.origin!==self.location.origin)return;
 // El acceso /demo/ también debe abrir el formulario sin internet.
 if(request.mode==='navigate'&&ENTRIES.has(url.pathname)){
  event.respondWith((async()=>{
   const cache=await caches.open(CACHE);
   try{
    const response=await fetch(request);
    if(response.ok){
     await cache.put(SHELL,response.clone());
     return response;
    }
    return (await cache.match(SHELL))||response;
   }catch(e){return (await cache.match(SHELL))||Response.error();}
  })());
 }
 // Recursos de la app solamente. No interceptar otros endpoints.
});
