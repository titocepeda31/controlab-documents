/* Guarda únicamente el HTML público; nunca API, datos, sesiones o credenciales. */
const CACHE='demo-mecanica-suelo-v8';
const SHELL=new URL('formulario.html',self.registration.scope).pathname;
self.addEventListener('install',event=>event.waitUntil((async()=>{
 const cache=await caches.open(CACHE);
 await cache.add(new Request(SHELL,{cache:'reload'}));
 await self.skipWaiting();
})()));
self.addEventListener('activate',event=>event.waitUntil((async()=>{
 const keys=await caches.keys();
 await Promise.all(keys.filter(k=>k.startsWith('demo-mecanica-suelo-')&&k!==CACHE).map(k=>caches.delete(k)));
 await self.clients.claim();
})()));
self.addEventListener('fetch',event=>{
 const request=event.request,url=new URL(request.url);
 if(request.method!=='GET'||url.origin!==self.location.origin||url.pathname!==SHELL)return;
 event.respondWith((async()=>{
  try{
   const response=await fetch(request);
   if(response.ok){const cache=await caches.open(CACHE);await cache.put(SHELL,response.clone());}
   return response;
  }catch(e){const cache=await caches.open(CACHE);return (await cache.match(SHELL))||Response.error();}
 })());
});
