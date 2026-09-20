const CACHE='fetischshop-v2';
const STATIC=['/offline.html','/assets/app.css','/assets/app.js','/assets/app-icon.svg'];

self.addEventListener('install',event=>{
  event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(STATIC)).then(()=>self.skipWaiting()).catch(()=>{}));
});

self.addEventListener('activate',event=>{
  event.waitUntil(
    caches.keys()
      .then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key))))
      .then(()=>self.clients.claim())
  );
});

self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET')return;
  const url=new URL(event.request.url);
  if(url.origin!==self.location.origin)return;

  if(
    url.pathname.startsWith('/media/')
    || url.pathname.startsWith('/cron/')
    || url.pathname.startsWith('/admin/')
  ) return;

  if(event.request.mode==='navigate'){
    event.respondWith(fetch(event.request).catch(()=>caches.match('/offline.html')));
    return;
  }

  if(url.pathname.startsWith('/assets/')){
    event.respondWith(caches.match(event.request).then(cached=>cached||fetch(event.request)));
  }
});