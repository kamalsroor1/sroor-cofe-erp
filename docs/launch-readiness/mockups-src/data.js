// [name, sub, price, stock, kind, color, unit, weighted]
window.P=[
['iPhone 16 Pro Max','256GB · Desert Titanium',66500,15,'apple','#d6c2a8','قطعة',0],
['Samsung Galaxy S24 Ultra','512GB · Titanium Gray',58900,8,'samsung','#9aa3ad','قطعة',0],
['بن تركي محوّج','وزن بالميزان',560,9.25,'coffee','#7c4a2d','كجم',1],
['Xiaomi Redmi Note 13 Pro','256GB · Midnight Black',13400,22,'xiaomi','#2f3542','قطعة',0],
['قصدير لحام 0.3مم','صيانة · بالجرام',3,2500,'solder','#94a3b8','جم',1],
['AirPods Pro 2','USB-C · MagSafe',11900,30,'audio','#e5e7eb','قطعة',0],
['كابل AUX مضفر','يُباع بالمتر',35,150,'cable','#334155','متر',0],
['لب سوري محمص','وزن بالميزان',200,18.75,'seeds','#a16207','كجم',1],
['كرتونة اسكرينات 9D','50 قطعة · سامسونج',3500,12,'box','#475569','كرتونة',0],
['Anker 323 52.5W','Power Bank 10000mAh',890,45,'power','#20262e','قطعة',0],
['iPhone 15','128GB · Blue',38900,3,'apple','#a9c5e0','قطعة',0],
['مياه معدنية 600مل','زجاجة',7,120,'water','#0ea5e9','زجاجة',0]];
window.icon=k=>({apple:'',samsung:'S',xiaomi:'mi',audio:'🎧',power:'⚡',coffee:'☕',solder:'🧪',cable:'🔌',seeds:'🌻',box:'📦',water:'💧'}[k]);
window.fmtQty=(q,u)=>u==='كجم'?q.toFixed(3):u==='جم'?q.toLocaleString('en'):q;
