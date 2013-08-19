function open_Payment4b_window(){
	var iWinState = 1;
	var objForm = null;
	objForm=document.getElementById("payment4b_standard_checkout");


	objForm.target = "";
	objForm.submit();
}