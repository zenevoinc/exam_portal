// Global site JS
console.log('Exam Portal script loaded.');
window.addEventListener('beforeunload', function (e) {
  const flag = document.body.getAttribute('data-in-exam');
  if (flag === '1') {
    e.preventDefault();
    e.returnValue = '';
  }
});