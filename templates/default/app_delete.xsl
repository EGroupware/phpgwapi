<!-- $Id$ -->

	<xsl:template name="app_delete">
		<xsl:apply-templates select="delete"/>
	</xsl:template>

	<xsl:template match="delete">
			<table cellpadding="2" cellspacing="2" align="center">
				<xsl:choose>
					<xsl:when test="msgbox_data != ''">
						<tr>
							<td align="center" colspan="2"><xsl:call-template name="msgbox"/></td>
						</tr>
					</xsl:when>
				</xsl:choose>
				<tr>
					<td align="center" colspan="2"><xsl:value-of select="lang_confirm_msg"/></td>
				</tr>

<!-- delete sub -->

				<xsl:variable name="delete_url"><xsl:value-of select="delete_url"/></xsl:variable>
				<form method="POST" action="{$delete_url}">
				<xsl:choose>
					<xsl:when test="subs = 'yes'">
						<tr>
							<td align="center" colspan="2" class="row_off">
								<table>
									<tr>
										<td><input type="radio" name="subs" value="move"/></td>
										<td><xsl:value-of select="lang_sub_select_move"/></td>
									</tr>
									<tr>
										<td><input type="radio" name="subs" value="drop"/></td>
										<td><xsl:value-of select="lang_sub_select_drop"/></td>
									</tr>
								</table>
							</td>
						</tr>
					</xsl:when>
				</xsl:choose>

<!-- delete account -->

				<xsl:choose>
					<xsl:when test="owner_list">
						<tr>
							<td align="center" colspan="2" class="row_off">
								<table>
									<tr>
										<td><xsl:value-of select="lang_new_owner"/></td>
										<td><xsl:value-of select="lang_sub_select_move"/></td>
									</tr>
								</table>
							</td>
						</tr>
					</xsl:when>
				</xsl:choose>

<!-- delete group -->

				<xsl:choose>
					<xsl:when test="user_list">
						<tr>
							<td align="center" colspan="2" class="row_off">
								<table>
									<xsl:apply-templates select="user_list"/>
								</table>
							</td>
						</tr>
						<tr>
							<td align="center" colspan="2" class="row_on">
								<xsl:value-of select="lang_remove_user"/>
							</td>
						</tr>
					</xsl:when>
				</xsl:choose>
				<tr>
				<xsl:choose>
					<xsl:when test="show_done != ''">
					<xsl:variable name="lang_done"><xsl:value-of select="lang_done"/></xsl:variable>
						<td>
						<input type="submit" name="done" value="{$lang_done}" onMouseout="window.status='';return true;">
							<xsl:attribute name="onMouseover">
								<xsl:text>window.status='</xsl:text>
									<xsl:value-of select="lang_done_statustext"/>
								<xsl:text>'; return true;</xsl:text>
							</xsl:attribute>
						</input>
						</td>
					</xsl:when>
					<xsl:otherwise>
					<xsl:variable name="lang_delete"><xsl:value-of select="lang_delete"/></xsl:variable>
					<td>
						<input type="submit" name="delete" value="{$lang_delete}" onMouseout="window.status='';return true;">
							<xsl:attribute name="onMouseover">
								<xsl:text>window.status='</xsl:text>
									<xsl:value-of select="lang_delete_statustext"/>
								<xsl:text>'; return true;</xsl:text>
							</xsl:attribute>
						</input>
					</td>
					<td align="right">
					<xsl:variable name="lang_cancel"><xsl:value-of select="lang_cancel"/></xsl:variable>
						<input type="submit" name="cancel" value="{$lang_cancel}" onMouseout="window.status='';return true;">
							<xsl:attribute name="onMouseover">
								<xsl:text>window.status='</xsl:text>
									<xsl:value-of select="lang_cancel_statustext"/>
								<xsl:text>'; return true;</xsl:text>
							</xsl:attribute>
						</input>
					</td>
					</xsl:otherwise>
				</xsl:choose>
				</tr>
			</form>
			</table>
	</xsl:template>

	<xsl:template match="user_list">
	<xsl:variable name="user_url"><xsl:value-of select="user_url"/></xsl:variable>
		<tr>
			<td>
				<a href="{$user_url}" onMouseout="window.status='';return true;">
					<xsl:attribute name="onMouseover">
						<xsl:text>window.status='</xsl:text>
							<xsl:value-of select="lang_user_url_statustext"/>
						<xsl:text>'; return true;</xsl:text>
					</xsl:attribute>
					<xsl:value-of select="user_name"/>
				</a>
			</td>
		</tr>
	</xsl:template>
