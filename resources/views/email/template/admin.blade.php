<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <style>
        @import url("https://use.typekit.net/zxn7pho.css");
    </style>

    <style type="text/css">
        :root {
            color-scheme: light dark;
            supported-color-schemes: light dark;
        }

        @if(isset($settings) && $settings->email_style === 'dark')
            body,
            [data-ogsc] {
                background-color: #1a1a1a !important;
                color: #ffffff !important;
            }

            div, tr, td,
            [data-ogsc] div,
            [data-ogsc] tr,
            [data-ogsc] td {
                border-color: #222222 !important;
            }

            h1, h2, h3, p, td,
            [data-ogsc] h1, [data-ogsc] h2, [data-ogsc] h3, [data-ogsc] p, [data-ogsc] td, {
                color: #ffffff !important;
            }

            p,
            [data-ogsc] p {
                color: #bbbbbc !important;
            }

            .dark-bg-base,
            [data-ogsc] .dark-bg-base {
                background-color: #222222 !important;
            }

            .dark-bg,
            [data-ogsc] .dark-bg {
                background-color: #3a3a3c !important;
            }

            .logo-dark,
            [data-ogsc] .logo-dark {
                display: block !important;
            }

            .logo-light,
            [data-ogsc] .logo-light {
                display: none !important;
            }

            .btn-white,
            [data-ogsc] .btn-white {
                background-color: #fefefe !important;
            }
        @endif

        /** Content-specific styles. **/
        #content .button {
            display: inline-block;
            background-color: #0091ea;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-decoration: none;
            font-size: 13px;
            padding: 15px 70px;
            font-weight: 600;
            margin-bottom: 30px;
        }

        #content h1 {
            font-family: 'canada-type-gibson', 'roboto', Arial, Helvetica, sans-serif;
            font-weight: 600;
            font-size: 32px;
            margin-top: 5px;
            margin-bottom: 30px;
        }

        #content > p {
            font-size: 16px;
            color: red;
        }

        #content .center {
            text-align: center;
        }
    </style>
</head>

<body class="body"
      style="margin: 0; padding: 0; font-family: 'roboto', Arial, Helvetica, sans-serif; color: #3b3b3b;-webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td>
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="570"
                   style="border-collapse: collapse;">
                <tr>
                    <div style="text-align: center;margin-top: 25px; margin-bottom: 10px;">
                        <!-- Top side spacing. -->
                    </div>
                </tr>
                <tr>
                    <td>
                        <div class="dark-bg"
                             style="background-color:#f9f9f9; border: 1px solid #c2c2c2; border-bottom: none; padding-bottom: 20px; border-top-left-radius: 3px; border-top-right-radius: 3px;">
                            <img class="logo-light"
                                 style="margin-top: 20px; max-width: 155px; display: block; margin-left: auto; margin-right: auto; "
                                 src="{{ $logo ?? '' }}"/>
                            <img class="logo-dark"
                                 style="display: none; margin-top: 20px; max-width: 155px; margin-left: auto; margin-right: auto; "
                                 src="{{ $logo ?? '' }}"/>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="dark-bg-base" id="content"
                             style="border: 1px solid #c2c2c2; border-top: none; border-bottom: none; padding: 20px;">
                                {{ $slot }}
                        </div> <!-- End replaceable content. -->
                    </td>
                </tr>
                <tr class="dark-bg"
                    style="background-color: #0091ea; border: 1px solid #c2c2c2; border-top: none; border-bottom-color: #0091ea;">
                    <td>
                        <div style="text-align: center; margin-top: 25px;">
                            <h2
                                style="color: #ffffff; font-family: 'canada-type-gibson', 'roboto', Arial, Helvetica, sans-serif; font-weight: 500; font-size: 26px;">
                                Questions? We're here to help!</h2>
                        </div>


                        <div style="text-align:center; margin-bottom: 35px; margin-top: 25px;">
                            <a href="https://forum.invoiceninja.com" target="_blank" class="btn-white"
                               style="vertical-align: middle;display: inline-block;background-color: #ffffff; color: #0091ea; display: inline-block; text-decoration: none;  width: 100px; text-align: center; font-size: 12px; height: 35px; line-height: 35px; margin-left: 10px; margin-right: 10px;">
                                <span>Forums</span>
                            </a>


                            <a href="http://slack.invoiceninja.com/" target="_blank" class="btn-white"
                               style="vertical-align: middle;display: inline-block;background-color: #ffffff; color: #0091ea; display: inline-block; text-decoration: none;  width: 100px; text-align: center; font-size: 12px; height: 35px; line-height: 35px; margin-left: 10px; margin-right: 10px;">
                                <span>Slack</span>
                            </a>


                            <a href="https://www.invoiceninja.com/contact/" target="_blank" class="btn-white"
                               style="vertical-align: middle;display: inline-block;background-color: #ffffff; color: #0091ea; display: inline-block; text-decoration: none;  width: 100px; text-align: center; font-size: 12px; height: 35px; line-height: 35px; margin-left: 10px; margin-right: 10px;">
                                <span>E-mail</span>
                            </a>

                            <a href="https://invoiceninja.github.io/" target="_blank" class="btn-white"
                               style="vertical-align: middle;display: inline-block;background-color: #ffffff; color: #0091ea; display: inline-block; text-decoration: none;  width: 100px; text-align: center; font-size: 12px; height: 35px; line-height: 35px; margin-left: 10px; margin-right: 10px;">
                                <span>Support Docs</span>
                            </a>
                        </div>

                    </td>
                </tr>
                <tr>
                    <td class="dark-bg-base"
                        style="background-color: #242424; border: 1px solid #c2c2c2; border-top-color: #242424; border-bottom-color: #242424;">
                        <div style="padding-top: 10px;padding-bottom: 10px;">
                            <p style="text-align: center; color: #ffffff; font-size: 10px;
                            font-family: Verdana, Geneva, Tahoma, sans-serif;">© {{ date('Y') }} Invoice Ninja, All Rights Reserved
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>

</html>
