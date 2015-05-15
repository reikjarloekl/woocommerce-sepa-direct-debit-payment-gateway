# -*- coding: utf-8 -*-
from __future__ import unicode_literals

from django.db import models, migrations


class Migration(migrations.Migration):

    dependencies = [
        ('front', '0004_image'),
    ]

    operations = [
        migrations.AddField(
            model_name='emailaddress',
            name='verified',
            field=models.BooleanField(default=False),
            preserve_default=True,
        ),
    ]
