import{a7 as r,d as s,o as n,b as i,s as c,w as u,k as l,t as o,z as d}from"./index-C4AW3xbL.js";/**
 * @license lucide-vue-next v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const h=r("ArchiveIcon",[["rect",{width:"20",height:"5",x:"2",y:"3",rx:"1",key:"1wp1u1"}],["path",{d:"M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8",key:"1s80jp"}],["path",{d:"M10 12h4",key:"a56b0p"}]]),k=s({__name:"CurriculumStatusBadge",props:{status:{},size:{default:"sm"}},setup(a){const e=a,t=d(()=>{switch(e.status){case"draft":return{label:"Draft",variant:"warning"};case"active":return{label:"Aktif Berlaku",variant:"success"};case"inactive":return{label:"Non-Aktif",variant:"neutral"};case"archived":return{label:"Diarsipkan",variant:"info"};default:return{label:e.status,variant:"neutral"}}});return(v,p)=>(n(),i(c,{variant:t.value.variant,size:a.size,dot:""},{default:u(()=>[l(o(t.value.label),1)]),_:1},8,["variant","size"]))}});export{h as A,k as _};
