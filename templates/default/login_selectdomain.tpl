<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<!-- BEGIN login_form -->
<HEAD>

<META http-equiv="Content-Type" content="text/html; charset={charset}">
<META name="AUTHOR" content="phpGroupWare http://www.phpgroupware.org">
<META NAME="description" CONTENT="{website_title} login screen, working environment powered by eGroupWare">
<META NAME="keywords" CONTENT="{website_title} login screen, eGroupWare, groupware, groupware suite">

<TITLE>{website_title} - Login</TITLE>
</HEAD>

<BODY bgcolor="#FFFFFF">
<a href="http://{logo_url}"><img src="{logo_file}" alt="{logo_title}" title="{logo_title}" border="0"></a>
<p>&nbsp;</p>
<CENTER>{lang_message}</CENTER>
<p>&nbsp;</p>

<TABLE bgcolor="#000000" border="0" cellpadding="0" cellspacing="0" width="50%" align="CENTER">
 <TR>
  <TD>
   <TABLE border="0" width="100%" bgcolor="#486591" cellpadding="2" cellspacing="1">
    <TR bgcolor="#486591">
     <TD align="LEFT" valign="MIDDLE">
      <font color="#fefefe">&nbsp;phpGroupWare</font>
     </TD>
    </TR>
    <TR bgcolor="#e6e6e6">
     <TD valign="BASELINE">

      <FORM method="post" action="{login_url}">
	  <input type="hidden" name="passwd_type" value="text">
       <TABLE border="0" align="CENTER" bgcolor="#486591" width="100%" cellpadding="0" cellspacing="0">
        <TR bgcolor="#e6e6e6">
         <TD colspan="3" align="CENTER">
          {cd}
         </TD>
        </TR>
        <TR bgcolor="#e6e6e6">
         <TD align="RIGHT"><font color="#000000">{lang_username}:</font></TD>
         <TD align="RIGHT"><input name="login" value="{cookie}"></TD>
         <TD align="LEFT">&nbsp;@&nbsp;<select name="logindomain">{select_domain}</select></TD>
        </TR>
        <TR bgcolor="#e6e6e6">
         <TD align="RIGHT"><font color="#000000">{lang_password}:</font></TD>
         <TD align="RIGHT"><input name="passwd" type="password" onChange="this.form.submit()"></TD>
         <TD>&nbsp;</TD>
        </TR>
        <TR bgcolor="#e6e6e6">
         <TD colspan="3" align="CENTER">
          <input type="submit" value="{lang_login}" name="submitit">
         </TD>
        </TR>
        <TR bgcolor="#e6e6e6">
         <TD colspan="3" align="RIGHT">
          <font color="#000000" size="-1">{version}</font>
         </TD>
        </TR>       
       </TABLE>
      </FORM>
     
     </TD>
    </TR>
   </TABLE>
  </TD>
 </TR>
</TABLE>

<!-- END login_form -->
</HTML>
