const wide = [];
document.querySelectorAll('*').forEach(el => {
  if (el.scrollWidth > 375 || el.offsetWidth > 375 || el.getBoundingClientRect().right > 375) {
    wide.push({
      tag: el.tagName,
      id: el.id,
      className: el.className,
      scrollWidth: el.scrollWidth,
      offsetWidth: el.offsetWidth,
      right: el.getBoundingClientRect().right
    });
  }
});
console.log(JSON.stringify(wide.slice(0, 15), null, 2));
