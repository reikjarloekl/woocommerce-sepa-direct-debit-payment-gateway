# -*- coding: utf-8 -*-
from __future__ import unicode_literals

from django.db import models, migrations


class Migration(migrations.Migration):

    dependencies = [
        ('front', '0002_auto_20150319_2249'),
    ]

    operations = [
        migrations.AddField(
            model_name='emailaddress',
            name='name',
            field=models.CharField(default=b'', max_length=60),
            preserve_default=True,
        ),
    ]
