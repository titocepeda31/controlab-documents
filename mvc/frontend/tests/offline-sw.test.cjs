'use strict';
const fs=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');

const URL_BASE='https://controlab.example/demo/';
const listeners={};
const records=new Map();
const names=new Map();
let connected=true;
const RequestMock=class {constructor(input){this.url=new URL(input,URL_BASE).href;}};
function keyOf(input){return new URL(typeof input==='string'?input:input.url,URL_BASE).pathname;}
const caches={
 async open(name){
  if(!names.has(name))names.set(name,new Map());
  const store=names.get(name);
  return {
   async addAll(requests){for(const req of requests)store.set(keyOf(req),new Response('cached '+keyOf(req)));},
   async put(req,response){store.set(keyOf(req),response);},
   async match(req){return store.get(keyOf(req));}
  };
 },
 async keys(){return [...names.keys()];},
 async delete(key){return names.delete(key);}
};
const self={
 registration:{scope:URL_BASE},
 location:{origin:'https://controlab.example'},
 addEventListener:(name,listener)=>{listeners[name]=listener;},
 skipWaiting:async()=>{},
 clients:{claim:async()=>{}}
};
const source=fs.readFileSync('mvc/frontend/sw.js','utf8');
vm.runInNewContext(source,{self,caches,Request:RequestMock,Response,URL,
 fetch:async request=>{if(!connected)throw Error('Sin internet');return new Response('online '+keyOf(request),{status:200});}
},{filename:'sw.js'});

async function dispatchLifecycle(name){
 const promises=[];
 listeners[name]({waitUntil:p=>promises.push(p)});
 await Promise.all(promises);
}
async function request(url,method='GET',mode='navigate'){
 let response;
 listeners.fetch({request:{url,method,mode},respondWith:p=>{response=p;}});
 return response?await response:null;
}
(async()=>{
 await dispatchLifecycle('install');
 await dispatchLifecycle('activate');
 const cached=(await caches.open([...names.keys()][0]));
 assert.ok(await cached.match('/demo/formulario.html'),'El formulario debe estar guardado offline');
 assert.ok(await cached.match('/demo/manifest.json'),'El manifest debe estar guardado offline');
 assert.ok(await cached.match('/demo/icon.svg'),'El icono debe estar guardado offline');
 assert.equal(await request('https://controlab.example/demo/api/session'),null,'La API no se debe interceptar');
 assert.equal(await request('https://controlab.example/demo/', 'POST'),null,'Los POST no se deben interceptar');
 connected=false;
 for(const path of ['/demo/','/demo/formulario.html','/demo/index.php']){
  const reply=await request('https://controlab.example'+path);
  assert.ok(reply,'La ruta de entrada '+path+' debe abrir sin conexión');
  assert.ok((await reply.text()).startsWith('cached'),'Debe servirse contenido local');
 }
 assert.equal([...names.values()].some(m=>[...m.keys()].some(k=>k.includes('/api/'))),false,'La API no debe almacenarse');
 console.log('OK: formulario disponible offline en tres rutas, sin cachear API.');
})().catch(e=>{console.error(e);process.exitCode=1;});
