<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("Ce fichier ne peut pas être accédé directement");
}

function limit_purchase_config()
{
    return array(
        "name" => "Product Limiter remastered",
        "description" => "This addon allows you to limit the purchase of an products/services for each client",
        "version" => "1.0.1",
        "author" => "Idan Ben-Ezra - remaster : utrosh",
        "language" => "english",
    );
}

function limit_purchase_activate()
{
    $error = [];

    try {
        Capsule::schema()->create('mod_limit_purchase_config', function ($table) {
            $table->string('name')->primary();
            $table->text('value');
        });

        Capsule::table('mod_limit_purchase_config')->insert([
            ['name' => 'localkey', 'value' => ''],
            ['name' => 'version_check', 'value' => '0'],
            ['name' => 'version_new', 'value' => ''],
        ]);

        Capsule::schema()->create('mod_limit_purchase', function ($table) {
            $table->increments('id');
            $table->integer('product_id')->default(0);
            $table->integer('limit')->default(0);
            $table->string('error');
            $table->tinyInteger('active')->default(0);
        });
    } catch (\Exception $e) {
        $error[] = "Can't create tables. Error: " . $e->getMessage();
    }

    if (!empty($error)) {
        limit_purchase_deactivate();
    }

    return array(
        'status' => empty($error) ? 'success' : 'error',
        'description' => empty($error) ? '' : implode(" -> ", $error),
    );
}

function limit_purchase_deactivate()
{
    $error = [];

    try {
        Capsule::schema()->dropIfExists('mod_limit_purchase');
        Capsule::schema()->dropIfExists('mod_limit_purchase_config');
    } catch (\Exception $e) {
        $error[] = "Can't drop tables. Error: " . $e->getMessage();
    }

    return array(
        'status' => empty($error) ? 'success' : 'error',
        'description' => empty($error) ? '' : implode(" -> ", $error),
    );
}


function limit_purchase_upgrade($vars) 
{
	if(version_compare($vars['version'], '1.0.1', '<'))
	{
	   	$sql = "CREATE TABLE IF NOT EXISTS `mod_limit_purchase_config` (
				`name` varchar(255) NOT NULL,
				`value` text NOT NULL,
			PRIMARY KEY (`name`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8";
		$result = mysql_query($sql);

		if($result) 
		{
			$sql = "INSERT INTO mod_limit_purchase_config (`name`,`value`) VALUES
				('localkey', ''),
				('version_check', '0'),
				('version_new', '')";
			$result = mysql_query($sql);
		}
	}
}

function limit_purchase_output($vars)
{
    $modulelink = $vars['modulelink'];
    $version = $vars['version'];

    $lp = new LimitPurchase;

    if ($lp->config['version_check'] <= (time() - (60 * 60 * 24))) {
        $url = "http://clients.jetserver.net/version/limitpurchase.txt";

        $remote_version = file_get_contents($url);
        $remote_version = trim($remote_version);

        if ($remote_version) {
            $lp->setConfig('version_new', $remote_version);
            $lp->config['version_new'] = $remote_version;
        }

        $lp->setConfig('version_check', time());
    }

    if (version_compare($version, $lp->config['version_new'], '<')) {
?>
        <div class="infobox">
            <strong><span class="title"><?php echo $vars['_lang']['newversiontitle']; ?></span></strong><br />
            <?php echo sprintf($vars['_lang']['newversiondesc'], $lp->config['version_new']); ?>
        </div>
<?php
    }

    $ids = $limits = array();

    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
    $product_id = isset($_REQUEST['product_id']) ? intval($_REQUEST['product_id']) : 0;
    $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    $limit = isset($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 0;
    $error = isset($_REQUEST['error']) ? $_REQUEST['error'] : '';
    $active = isset($_REQUEST['active']) ? intval($_REQUEST['active']) : 0;

    $manage_details = array();


switch ($action) {
    case 'enable':
    case 'disable':
        if ($id) {
            $limitDetails = Capsule::table('mod_limit_purchase')
                ->where('id', $id)
                ->first();

            if ($limitDetails) {
                Capsule::table('mod_limit_purchase')
                    ->where('id', $id)
                    ->update(['active' => ($action == 'disable' ? 0 : 1)]);

                $_SESSION['limit_purchase'] = [
                    'type' => 'success',
                    'message' => $vars['_lang']['actionlimit' . ($action == 'disable' ? 'disabled' : 'enabled')],
                ];
            } else {
                $_SESSION['limit_purchase'] = [
                    'type' => 'error',
                    'message' => $vars['_lang']['actionnolimitid'],
                ];
            }
        } else {
            $_SESSION['limit_purchase'] = [
                'type' => 'error',
                'message' => $vars['_lang']['actionnolimitprovided'],
            ];
        }

        header('Location: ' . $modulelink);
        exit;
        break;

    case 'add':
        if ($product_id) {
            $productDetails = Capsule::table('tblproducts')
                ->where('id', $product_id)
                ->first();

            if ($productDetails) {
                $limitDetails = Capsule::table('mod_limit_purchase')
                    ->where('product_id', $product_id)
                    ->first();

                if (!$limitDetails) {
                    if ($limit > 0) {
                        Capsule::table('mod_limit_purchase')->insert([
                            'product_id' => $product_id,
                            'limit' => $limit,
                            'error' => 'Vous avez déjà un service !',
                            'active' => ($active ? 1 : 0),
                        ]);

                        $_SESSION['limit_purchase'] = [
                            'type' => 'success',
                            'message' => $vars['_lang']['actionadded'],
                        ];
                    } else {
                        $errors = [];
                        if (!$error) $errors[] = '&bull; ' . $vars['_lang']['limit'];
                        if (!$limit) $errors[] = '&bull; ' . $vars['_lang']['limit'];

                        $_SESSION['limit_purchase'] = [
                            'type' => 'error',
                            'message' => $vars['_lang']['actionfieldsreq'] . '<br />' . implode("<br />", $errors),
                        ];
                    }
                } else {
                    $_SESSION['limit_purchase'] = [
                        'type' => 'error',
                        'message' => $vars['_lang']['actionlimitexists'],
                    ];
                }
            } else {
                $_SESSION['limit_purchase'] = [
                    'type' => 'error',
                    'message' => $vars['_lang']['actionnoproductid'],
                ];
            }
        } else {
            $_SESSION['limit_purchase'] = [
                'type' => 'error',
                'message' => $vars['_lang']['actionselectproduct'],
            ];
        }

        header('Location: ' . $modulelink);
        exit;
        break;

		case 'edit':
    if ($id) {
        $limitDetails = Capsule::table('mod_limit_purchase')
            ->where('id', $id)
            ->first();

        if ($limitDetails) {
            if ($product_id) {
                $productDetails = Capsule::table('tblproducts')
                    ->where('id', $product_id)
                    ->first();

                if ($productDetails) {
                    if ($limit > 0 && $error) {
                        Capsule::table('mod_limit_purchase')
                            ->where('id', $id)
                            ->update([
                                'product_id' => $product_id,
                                'limit' => $limit,
                                'error' => $error,
                                'active' => $active ? 1 : 0,
                            ]);

                        $_SESSION['limit_purchase'] = [
                            'type' => 'success',
                            'message' => $vars['_lang']['actionlimitedited'],
                        ];
                    } else {
                        $errors = [];
                        if (!$limit) $errors[] = '&bull; ' . $vars['_lang']['limit'];
                        if (!$error) $errors[] = '&bull; ' . $vars['_lang']['errormessage'];

                        $_SESSION['limit_purchase'] = [
                            'type' => 'error',
                            'message' => $vars['_lang']['actionfieldsreq'] . '<br />' . implode("<br />", $errors),
                        ];
                    }
                } else {
                    $_SESSION['limit_purchase'] = [
                        'type' => 'error',
                        'message' => $vars['_lang']['actionnoproductid'],
                    ];
                }
            } else {
                $_SESSION['limit_purchase'] = [
                    'type' => 'error',
                    'message' => $vars['_lang']['actionselectproduct'],
                ];
            }
        } else {
            $_SESSION['limit_purchase'] = [
                'type' => 'error',
                'message' => $vars['_lang']['actionnolimitid'],
            ];
        }
    } else {
        $_SESSION['limit_purchase'] = [
            'type' => 'error',
            'message' => $vars['_lang']['actionnolimitprovided'],
        ];
    }

    header('Location: ' . $modulelink);
    exit;
    break;


	case 'delete':
    if ($id) {
        $limitDetails = Capsule::table('mod_limit_purchase')
            ->where('id', $id)
            ->first();

        if ($limitDetails) {
            Capsule::table('mod_limit_purchase')
                ->where('id', $id)
                ->delete();

            $_SESSION['limit_purchase'] = [
                'type' => 'success',
                'message' => $vars['_lang']['actionlimitdeleted'],
            ];
        } else {
            $_SESSION['limit_purchase'] = [
                'type' => 'error',
                'message' => $vars['_lang']['actionnolimitid'],
            ];
        }
    } else {
        $_SESSION['limit_purchase'] = [
            'type' => 'error',
            'message' => $vars['_lang']['actionnolimitprovided'],
        ];
    }

    header('Location: ' . $modulelink);
    exit;
    break;


	case 'manage':
    if ($id) {
        $limitDetails = Capsule::table('mod_limit_purchase')
            ->where('id', $id)
            ->first();

        if ($limitDetails) {
            $manageDetails = Capsule::table('mod_limit_purchase')
                ->where('id', $id)
                ->first();
        } else {
            $_SESSION['limit_purchase'] = [
                'type' => 'error',
                'message' => $vars['_lang']['actionnolimitid'],
            ];
        }
    } else {
        $_SESSION['limit_purchase'] = [
            'type' => 'error',
            'message' => $vars['_lang']['actionnolimitprovided'],
        ];
    }

    if (isset($_SESSION['limit_purchase'])) {
        header('Location: ' . $modulelink);
        exit;
    }
    break;
	}

$limits = [];
$products = [];
$ids = [];

$limitPurchaseRows = Capsule::table('mod_limit_purchase')->get();

foreach ($limitPurchaseRows as $row) {
    if ($manageDetails['product_id'] != $row->product_id) {
        $product = Capsule::table('tblproducts')
            ->where('id', $row->product_id)
            ->first();

        $ids[] = $row->product_id;
        $limits[] = array_merge((array)$row, ['product_details' => (array)$product]);
    }
}

if (isset($_SESSION['limit_purchase'])) {
    ?>
    <div class="<?= $_SESSION['limit_purchase']['type']; ?>box">
        <strong><span class="title"><?= $vars['_lang']['info']; ?></span></strong><br />
        <?= $_SESSION['limit_purchase']['message']; ?>
    </div>
    <?php
    unset($_SESSION['limit_purchase']);
}

$productRows = Capsule::table('tblproducts')
    ->whereNotIn('id', $ids)
    ->get();

foreach ($productRows as $product_details) {
    $products[] = (array)$product_details;
}
 
 ?>
	<h2><?php echo (sizeof($manage_details) ? $vars['_lang']['editlimit'] : $vars['_lang']['addlimit']); ?></h2>
	<form action="<?php echo $modulelink; ?>&amp;action=<?php echo (sizeof($manage_details) ? 'edit&amp;id=' . $manage_details['id'] : 'add'); ?>" method="post">

	<table width="100%" cellspacing="2" cellpadding="3" border="0" class="form">
	<tbody>
	<tr>
		<td width="15%" class="fieldlabel"><?php echo $vars['_lang']['product']; ?></td>
		<td class="fieldarea">
			<select name="product_id" class="form-control select-inline">
				<?php if(!sizeof($manage_details)) { ?>
				<option selected="selected" value="0"><?php echo $vars['_lang']['selectproduct']; ?></option>
				<?php } ?>
				<?php foreach($products as $product_details) { ?>
				<option<?php if($manage_details['product_id'] == $product_details['id']) { ?> selected="selected"<?php } ?> value="<?php echo $product_details['id']; ?>"><?php echo $product_details['name']; ?></option>
				<?php } ?>
			</select>
		</td>
	</tr>
	<tr>
		<td class="fieldlabel"><?php echo $vars['_lang']['limit']; ?></td>
		<td class="fieldarea"><input type="text" value="<?php echo $manage_details['limit']; ?>" size="5" name="limit" /> <?php echo $vars['_lang']['limitdesc']; ?></td>
	</tr>
	<tr>
		<td class="fieldlabel"><?php echo $vars['_lang']['errormessage']; ?></td>
		<td class="fieldarea"><input type="text" value="<?php echo $manage_details['error']; ?>" size="65" name="error" /><br /><?php echo $vars['_lang']['errormessagedesc']; ?></td>
	</tr>
	<tr>
		<td class="fieldlabel"><?php echo $vars['_lang']['active']; ?></td>
		<td class="fieldarea">
			<input type="radio" <?php if($manage_details['active']) { ?>checked="checked" <?php } ?>value="1" name="active" /> <?php echo $vars['_lang']['yes']; ?>
			<input type="radio" <?php if(!$manage_details['active']) { ?>checked="checked" <?php } ?>value="0" name="active" /> <?php echo $vars['_lang']['no']; ?>
		</td>
	</tr>
	</tbody>
	</table>

	<p align="center">
		<input type="submit" class="btn btn-primary" value="<?php echo (sizeof($manage_details) ? $vars['_lang']['save'] : $vars['_lang']['createlimitation']); ?>" />
		<?php if(sizeof($manage_details)) { ?>
			<a href="<?php echo $modulelink; ?>" class="btn btn-default"><?php echo $vars['_lang']['cancel']; ?></a>
		<?php } ?>
	</p>
	</form>

	<?php if(!sizeof($manage_details)) { ?>

	<div class="tablebg">

		<table width="100%" cellspacing="1" cellpadding="3" border="0" class="datatable">
		<tbody>
		<tr>
			<th><?php echo $vars['_lang']['product']; ?></th>
			<th><?php echo $vars['_lang']['limit']; ?></th>
			<th><?php echo $vars['_lang']['errormessage']; ?></th>
      
			<th width="20"></th>
			<th width="20"></th>
			<th width="20"></th>
		</tr>
<?php foreach($limits as $limit_details) { ?>
    <tr>
        <td><?php echo $limit_details['product_details']['name']; ?></td>
        <td style="text-align: center;"><?php echo $limit_details['limit']; ?></td>
        <td><?php echo str_replace('{PNAME}', $limit_details['product_details']['name'], $limit_details['error']); ?></td>

        <td><a href="<?php echo $modulelink; ?>&amp;action=delete&amp;id=<?php echo $limit_details['id']; ?>"><img width="16" height="16" border="0" alt="Delete" src="images/delete.gif" /></a></td>
    </tr>
<?php } ?>

		</tbody>
		</table>
	</div>

	<?php } ?>
<?php

}

?>
