function i(t,n=350){let e=null;return function(...u){e&&clearTimeout(e),e=setTimeout(()=>{t(...u)},n)}}export{i as d};
